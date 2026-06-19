<?php

namespace App\Filament\App\Pages;

use App\Domain\Acf\UnreadableAcfException;
use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\CheckKey;
use App\Domain\Approval\VerificationStage;
use App\Domain\Invoice\InvoiceData;
use App\Domain\Invoice\InvoiceReader;
use App\Filament\App\Resources\Verifications\VerificationResource;
use App\Models\AcfCertificate;
use App\Models\OrganizationProfile;
use App\Models\Verification;
use App\Services\Acf\AcfAuthenticationService;
use App\Services\Acf\AcfIngestionService;
use App\Services\Evidence\EvidenceTimestampService;
use App\Services\Fiscalis\FiscalisConnector;
use App\Services\Fiscalis\FiscalisStatusService;
use App\Services\Invoice\InvoiceNoticeService;
use App\Services\Verification\BatchVerificationService;
use App\Services\Verification\VerificationService;
use App\Services\Verification\VerifyRequest;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The Compliance Engine — the single working surface. A live toggle switches
 * between invoice **recording** (one vendor: ACF + scanned invoice, which OCR
 * auto-populates the control fields) and invoice **settlement** (a batch of
 * NIUs). Both run the four strict checks (plus the art. 150 conditions de forme
 * et de fond at recording) and reveal every test's output inline — a results
 * table and a closing list of discrepancies — without ever leaving the page.
 */
class ComplianceEngine extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.app.pages.compliance-engine';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = -20;

    /** recording | settlement — the live phase toggle. */
    public string $stage = 'recording';

    // Recording inputs.
    /** @var TemporaryUploadedFile|null */
    public $acfUpload = null;

    /**
     * One or more invoices recorded against the single loaded ACF — in practice a
     * tax-clearance certificate covers several of a vendor's invoices. Each invoice
     * is OCR'd and verified independently (its own conditions de forme et de fond),
     * while the ACF authenticity is checked once, when the certificate is uploaded.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public $invoiceUploads = [];

    public ?string $rawInput = null;

    public ?string $invoiceAmount = null;

    public ?string $invoiceName = null;

    // Captured as the uploads are read (auto-populate).
    public ?string $acfReference = null;

    /**
     * The scanned invoices, cached so run() needs no second OCR. Each entry:
     * ['name' => original filename, 'path' => stored path, 'data' => InvoiceData
     * array, 'extracted' => human-readable summary].
     *
     * @var array<int, array<string, mixed>>
     */
    public array $invoices = [];

    // Live evidence captured automatically as the documents are read (no manual
    // action): the Harmony2 authenticity verdict lives on the certificate; the
    // DGI Fiscalis snapshot is stashed here and attached to the verification on run.
    public ?string $acfAuthLabel = null;

    public ?string $fiscalisSnapshotPath = null;

    public ?string $fiscalisPresence = null;

    public ?string $fiscalisCheckedAt = null;

    // Settlement input.
    public ?string $nius = null;

    // Results (plain serialisable arrays — rendered inline).
    /** @var array<string, mixed>|null a single invoice's full recording result */
    public ?array $result = null;

    /** @var array<string, mixed>|null the per-invoice table when several invoices share one ACF */
    public ?array $recordingBatch = null;

    /** @var array<string, mixed>|null */
    public ?array $batch = null;

    /** @var array<int, array<string, mixed>> */
    public array $discrepancies = [];

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.compliance_engine');
    }

    public function getTitle(): string
    {
        return (string) __('app.nav.compliance_engine');
    }

    /**
     * The four checks, in order — used as the column headers of the settlement
     * batch table so each NIU shows the output of every test on one row.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function checkColumns(): array
    {
        return array_map(
            fn (CheckKey $k): array => ['key' => $k->value, 'label' => $k->label()],
            CheckKey::cases(),
        );
    }

    /** Open on the stage this user's role is allowed to work in. */
    public function mount(): void
    {
        $this->stage = auth()->user()?->defaultStage() ?? 'recording';
    }

    /** Whether the signed-in user may use the recording stage. */
    public function canRecord(): bool
    {
        return auth()->user()?->canRecord() ?? false;
    }

    /** Whether the signed-in user may use the settlement stage. */
    public function canSettle(): bool
    {
        return auth()->user()?->canSettle() ?? false;
    }

    /** Switch phase and clear any results so the screen never mixes the two. */
    public function setStage(string $stage): void
    {
        $target = $stage === 'settlement' ? 'settlement' : 'recording';

        // Refuse a stage this user's role does not cover.
        if (($target === 'settlement' && ! $this->canSettle()) || ($target === 'recording' && ! $this->canRecord())) {
            return;
        }

        $this->stage = $target;
        $this->reset(['result', 'recordingBatch', 'batch', 'discrepancies', 'acfAuthLabel', 'fiscalisSnapshotPath', 'fiscalisPresence', 'fiscalisCheckedAt']);
    }

    /**
     * The ACF was selected — read it now: record the certificate and auto-fill
     * the NIU under verification.
     */
    public function updatedAcfUpload(): void
    {
        if (! $this->acfUpload instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validate(['acfUpload' => ['file', 'mimes:pdf', 'max:10240']]);

        $path = $this->acfUpload->store('acf', 'local');
        if (! is_string($path)) {
            Notification::make()->title(__('app.ui.notif_acf_unreadable'))->danger()->send();

            return;
        }

        try {
            $certificate = app(AcfIngestionService::class)->ingestFromUpload($path, 'local', auth()->id());
        } catch (UnreadableAcfException $e) {
            Notification::make()->title(__('app.ui.notif_acf_unreadable'))->body($e->getMessage())->danger()->send();

            return;
        }

        $this->acfReference = $certificate->reference;
        if (blank($this->rawInput)) {
            $this->rawInput = $certificate->niu;
        }

        Notification::make()->title(__('app.ui.notif_acf_read'))->success()->send();

        // Authenticate the document live against Harmony2 right away — no manual
        // action. The screenshot + verdict are stored on the certificate, which
        // the verification then links to.
        $this->authenticateDocument($certificate);
    }

    /**
     * Drive the DGI Harmony2 portal for this certificate's reference and store
     * the verdict + screenshot on it. Graceful: a portal outage records
     * "unverified" and never blocks the recording.
     */
    private function authenticateDocument(AcfCertificate $certificate): void
    {
        if (! $this->liveSnapshots()) {
            return;
        }

        $result = app(AcfAuthenticationService::class)->authenticate($certificate);
        $this->acfAuthLabel = $result->status->label();

        Notification::make()
            ->title(__('app.ui.notif_doc_authenticated', ['status' => $result->status->label()]))
            ->status($result->status->color() === 'success' ? 'success' : ($result->status->color() === 'danger' ? 'danger' : 'warning'))
            ->send();
    }

    /**
     * One or more invoices were selected — OCR each now and auto-populate the
     * control fields from the first. The full extractions are cached for run(),
     * so several invoices can be recorded against the one loaded ACF in a single
     * pass. Unreadable files are reported and skipped, not fatal to the batch.
     */
    public function updatedInvoiceUploads(): void
    {
        if ($this->invoiceUploads === []) {
            return;
        }

        $this->validate(['invoiceUploads.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);

        $this->invoices = [];
        $reader = app(InvoiceReader::class);

        foreach ($this->invoiceUploads as $upload) {
            $name = $upload->getClientOriginalName();

            $path = $upload->store('invoices', 'local');
            if (! is_string($path)) {
                Notification::make()->title(__('app.ui.notif_invoice_unreadable'))->body($name)->danger()->send();

                continue;
            }

            try {
                $invoice = $reader->read($path, 'local');
            } catch (\Throwable $e) {
                Notification::make()->title(__('app.ui.notif_invoice_unreadable'))->body($name.' — '.$e->getMessage())->danger()->send();

                continue;
            }

            $this->invoices[] = [
                'name' => $name,
                'path' => $path,
                'data' => $invoice->toArray(),
                'extracted' => $this->extractedSummary($invoice),
            ];

            // Auto-fill the control fields from the first readable invoice.
            if (count($this->invoices) === 1) {
                if (blank($this->rawInput) && $invoice->supplierNiu !== null) {
                    $this->rawInput = $invoice->supplierNiu->value;
                }
                if (blank($this->invoiceAmount) && $invoice->ttcAmount !== null) {
                    $this->invoiceAmount = (string) $invoice->ttcAmount;
                }
                if (blank($this->invoiceName) && $this->filled($invoice->supplierName)) {
                    $this->invoiceName = $invoice->supplierName;
                }
            }
        }

        if ($this->invoices === []) {
            return;
        }

        // Make a partial batch visible: if some files could not be read, say so
        // (the per-file error toasts auto-dismiss and are easy to miss).
        $attempted = count($this->invoiceUploads);
        $loaded = count($this->invoices);
        if ($loaded < $attempted) {
            Notification::make()
                ->title(__('app.ui.notif_invoices_partial', ['loaded' => $loaded, 'total' => $attempted]))
                ->warning()
                ->persistent()
                ->send();
        }

        Notification::make()
            ->title(__('app.ui.notif_invoices_scanned', ['count' => $loaded]))
            ->success()
            ->send();

        // Capture the vendor's live DGI national-file snapshot once — every invoice
        // here is the same vendor's (the one ACF covers), so one snapshot serves all.
        $this->captureFiscalisSnapshot($this->filled($this->rawInput) ? trim((string) $this->rawInput) : null);
    }

    /**
     * Consult the DGI national taxpayer file for this NIU and stash the presence
     * verdict + screenshot, to be attached to the verification on run(). Graceful.
     */
    private function captureFiscalisSnapshot(?string $niu): void
    {
        $this->reset(['fiscalisSnapshotPath', 'fiscalisPresence', 'fiscalisCheckedAt']);

        if (! $this->liveSnapshots() || $niu === null) {
            return;
        }

        $result = app(FiscalisConnector::class)->check($niu);
        $this->fiscalisPresence = $result->presence->value;
        $this->fiscalisCheckedAt = now()->toDateTimeString();

        if ($result->hasScreenshot()) {
            $bytes = (string) $result->screenshotPng;
            $path = 'fiscalis-snapshots/niu-'.preg_replace('/[^A-Za-z0-9]/', '', $niu).'-'.now()->format('Ymd-His').'.png';
            Storage::disk('local')->put($path, $bytes);
            app(EvidenceTimestampService::class)->seal('local', $path, $bytes);
            $this->fiscalisSnapshotPath = $path;
        }

        Notification::make()
            ->title(__('app.ui.notif_fiscalis_captured', ['status' => $result->presence->label()]))
            ->status($result->presence->color() === 'success' ? 'success' : ($result->presence->color() === 'danger' ? 'danger' : 'warning'))
            ->send();
    }

    private function liveSnapshots(): bool
    {
        return (bool) config('services.browsershot.enabled');
    }

    /** Persist the stashed Fiscalis snapshot onto the freshly created verification. */
    private function attachFiscalisSnapshot(Verification $verification): void
    {
        if ($this->fiscalisPresence === null && $this->fiscalisSnapshotPath === null) {
            return;
        }

        $verification->forceFill([
            'fiscalis_presence' => $this->fiscalisPresence,
            'fiscalis_snapshot_path' => $this->fiscalisSnapshotPath,
            'fiscalis_checked_at' => $this->fiscalisCheckedAt,
        ])->save();
    }

    /** Run the checks for the current phase and reveal the results inline. */
    public function run(): void
    {
        // Guard: never run a stage outside the user's role.
        if (($this->stage === 'settlement' && ! $this->canSettle()) || ($this->stage !== 'settlement' && ! $this->canRecord())) {
            Notification::make()->title(__('app.ui.notif_stage_forbidden'))->danger()->send();

            return;
        }

        $this->validate([
            'rawInput' => ['nullable', 'string', 'max:32'],
            'invoiceAmount' => ['nullable', 'numeric', 'min:0'],
            'invoiceName' => ['nullable', 'string', 'max:255'],
            'nius' => $this->stage === 'settlement' ? ['required', 'string'] : ['nullable', 'string'],
        ]);

        $this->reset(['result', 'recordingBatch', 'batch', 'discrepancies']);

        $this->stage === 'settlement'
            ? $this->runSettlement()
            : $this->runRecording();
    }

    private function runRecording(): void
    {
        $rawInput = filled($this->rawInput) ? trim((string) $this->rawInput) : null;

        if (blank($rawInput)) {
            Notification::make()
                ->title(__('app.ui.notif_niu_missing'))
                ->body(__('app.ui.notif_niu_missing_body'))
                ->warning()
                ->send();

            return;
        }

        // No invoice loaded → an ACF-only recording (a single verification).
        // One or more invoices → verify each against the one loaded ACF.
        $invoices = $this->invoices !== []
            ? $this->invoices
            : [['name' => null, 'path' => null, 'data' => null]];

        $verifications = [];
        foreach ($invoices as $index => $invoice) {
            $invoiceData = is_array($invoice['data'] ?? null) && $invoice['data'] !== []
                ? InvoiceData::fromArray($invoice['data'])
                : null;

            $verification = app(VerificationService::class)->verify(new VerifyRequest(
                rawInput: $rawInput,
                stage: VerificationStage::Recording,
                acfReference: $this->acfReference,
                invoiceAmountXaf: $this->invoiceAmountFor($invoiceData, $index),
                invoiceName: $this->invoiceNameFor($invoiceData, $index),
                invoiceData: $invoiceData,
                invoiceFilePath: $invoice['path'] ?? null,
                checkedBy: auth()->id(),
            ));

            // Attach the vendor's live Fiscalis snapshot captured at scan time; it
            // is the same vendor across every invoice, so each record carries it.
            $this->attachFiscalisSnapshot($verification);

            // ACF-only recording with no snapshot yet → capture it now so the
            // record is complete.
            if ($this->liveSnapshots() && $verification->fiscalis_snapshot_path === null && filled($verification->niu)) {
                app(FiscalisStatusService::class)->capture($verification);
            }

            $verifications[] = ['verification' => $verification, 'label' => $invoice['name'] ?? null];
        }

        $this->presentRecording($verifications);
    }

    /** The TTC amount fed to the risk rules — per-invoice, or the manual field for a lone recording. */
    private function invoiceAmountFor(?InvoiceData $invoice, int $index): ?int
    {
        if ($invoice?->ttcAmount !== null) {
            return (int) $invoice->ttcAmount;
        }

        return $index === 0 && filled($this->invoiceAmount) ? (int) $this->invoiceAmount : null;
    }

    /** The supplier name fed to the risk rules — per-invoice, or the manual field for a lone recording. */
    private function invoiceNameFor(?InvoiceData $invoice, int $index): ?string
    {
        if ($this->filled($invoice?->supplierName)) {
            return (string) $invoice?->supplierName;
        }

        return $index === 0 && filled($this->invoiceName) ? (string) $this->invoiceName : null;
    }

    /**
     * Reveal the recording outcome: one invoice shows the full detailed result;
     * several invoices recorded against the one ACF show a per-invoice table with
     * the combined discrepancies underneath, each tagged with its invoice.
     *
     * @param  array<int, array{verification: Verification, label: ?string}>  $verifications
     */
    private function presentRecording(array $verifications): void
    {
        $discrepancies = [];
        foreach ($verifications as $entry) {
            $label = $entry['label'];
            foreach ($this->discrepanciesFor($entry['verification']) as $item) {
                $discrepancies[] = $label !== null ? $item + ['invoice' => $label] : $item;
            }
        }
        $this->discrepancies = $discrepancies;

        if (count($verifications) === 1) {
            $verification = $verifications[0]['verification'];
            $this->result = $this->present($verification);

            $notification = Notification::make()->title($verification->approval?->label() ?? $verification->verdict->label());
            $verification->approval === ApprovalDecision::Accepted
                ? $notification->success()
                : $notification->danger();
            $notification->send();

            return;
        }

        $accepted = 0;
        $rejected = 0;
        $exposure = 0;
        $rows = [];
        foreach ($verifications as $entry) {
            $verification = $entry['verification'];
            $verification->approval === ApprovalDecision::Accepted ? $accepted++ : $rejected++;
            $exposure += (int) $verification->exposure_xaf;
            $rows[] = $this->present($verification) + ['invoice_label' => $entry['label']];
        }

        $this->recordingBatch = [
            'summary' => [
                'total' => count($verifications),
                'accepted' => $accepted,
                'rejected' => $rejected,
                'exposure_xaf' => $exposure,
            ],
            'rows' => $rows,
        ];

        Notification::make()
            ->title(__('app.ui.notif_batch_done', ['accepted' => $accepted, 'rejected' => $rejected]))
            ->body(__('app.ui.notif_recording_batch_body', ['total' => count($verifications)]))
            ->status($rejected > 0 ? 'warning' : 'success')
            ->send();
    }

    private function runSettlement(): void
    {
        $batch = app(BatchVerificationService::class);
        $nius = $batch->parse((string) $this->nius);

        if ($nius === []) {
            Notification::make()->title(__('app.ui.notif_no_niu'))->warning()->send();

            return;
        }

        $results = $batch->verifyBatch($nius, VerificationStage::Settlement, auth()->id());
        $summary = $batch->summarise($results);

        $discrepancies = [];
        foreach ($results as $verification) {
            foreach ($this->discrepanciesFor($verification) as $item) {
                $discrepancies[] = $item + ['niu' => $verification->niu ?? $verification->raw_input];
            }
        }

        $this->batch = [
            'summary' => $summary,
            'rows' => $results->map(fn (Verification $v): array => $this->present($v))->all(),
        ];
        $this->discrepancies = $discrepancies;

        Notification::make()
            ->title(__('app.ui.notif_batch_done', ['accepted' => $summary['accepted'], 'rejected' => $summary['rejected']]))
            ->body(__('app.ui.notif_batch_body', ['total' => $summary['total']]))
            ->status($summary['rejected'] > 0 ? 'warning' : 'success')
            ->send();
    }

    /**
     * A flat, human-readable summary of what the scan pulled off the invoice.
     *
     * @return array<string, mixed>
     */
    private function extractedSummary(InvoiceData $invoice): array
    {
        return [
            'supplier_niu' => $invoice->supplierNiu?->value,
            'client_niu' => $invoice->clientNiu?->value,
            'supplier_name' => $invoice->supplierName,
            'client_identity' => $invoice->clientIdentity,
            'client_address' => $invoice->clientAddress,
            'client_rccm' => $invoice->clientRccm,
            'supplier_rccm' => $invoice->supplierRccm,
            'date' => $invoice->date?->toDateString(),
            'ht_amount' => $invoice->htAmount,
            'vat_amount' => $invoice->vatAmount,
            'ttc_amount' => $invoice->ttcAmount,
        ];
    }

    /**
     * Shape a verification into the plain arrays the Blade renders.
     *
     * @return array<string, mixed>
     */
    private function present(Verification $verification): array
    {
        $checks = $verification->checks ?? [];

        // The headline verdict is COMPLIANT only when every check passed — it
        // tracks the strict approval, never the looser NIU-risk grade alone.
        $accepted = $verification->approval === ApprovalDecision::Accepted;

        // The final decision is phrased for the stage: recording answers "may this
        // invoice be recorded?", settlement answers "may this invoice be paid?".
        $isSettlement = $verification->stage === VerificationStage::Settlement;

        return [
            'niu' => $verification->niu,
            'raw_input' => $verification->raw_input,
            'verdict' => [
                'label' => $accepted ? (string) __('app.verdict.conforme') : (string) __('app.verdict.non_conforme'),
                'color' => $accepted ? 'success' : 'danger',
                'icon' => $accepted ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle',
            ],
            'conclusion' => [
                'payable' => $accepted,
                'label' => $this->conclusionLabel($isSettlement, $accepted),
                'decision' => $this->decisionLabel($isSettlement, $accepted),
            ],
            'approval' => $verification->approval !== null ? [
                'label' => $verification->approval->label(),
                'color' => $verification->approval->color(),
                'icon' => $verification->approval->icon(),
                'accepted' => $verification->approval === ApprovalDecision::Accepted,
            ] : null,
            'exposure_xaf' => (int) $verification->exposure_xaf,
            'checks' => $checks,
            'checks_by_key' => collect($checks)->keyBy('key')->all(),
            'invoice_checks' => $verification->invoice_checks,
            'conformity_passed' => $this->conformityPassed($verification),
            'triggered_rules' => $verification->triggered_rules ?? [],
            'vendor_email' => $this->draftVendorEmail($verification),
            'view_url' => VerificationResource::getUrl('view', ['record' => $verification]),
        ];
    }

    /**
     * The full final-decision sentence, phrased for the stage: recording decides
     * whether the invoice may be recorded; settlement whether it may be paid.
     */
    private function conclusionLabel(bool $isSettlement, bool $accepted): string
    {
        return (string) match (true) {
            $isSettlement && $accepted => __('app.ui.conclusion_payable'),
            $isSettlement => __('app.ui.conclusion_not_payable'),
            $accepted => __('app.ui.conclusion_recordable'),
            default => __('app.ui.conclusion_not_recordable'),
        };
    }

    /** The short decision verb shown per row in the settlement batch table. */
    private function decisionLabel(bool $isSettlement, bool $accepted): string
    {
        return (string) match (true) {
            $isSettlement && $accepted => __('app.ui.decision_pay'),
            $isSettlement => __('app.ui.decision_no_pay'),
            $accepted => __('app.ui.decision_record'),
            default => __('app.ui.decision_no_record'),
        };
    }

    /**
     * Whether every art. 150 condition de forme et de fond passed for this
     * invoice — null when no invoice was scanned (an ACF-only recording).
     */
    private function conformityPassed(Verification $verification): ?bool
    {
        $findings = $verification->invoice_checks;

        if (! is_array($findings) || $findings === []) {
            return null;
        }

        foreach ($findings as $finding) {
            if (($finding['passed'] ?? true) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * A drafted accept/reject email to the vendor — only for recorded invoices.
     *
     * @return array{subject: string, body: string}|null
     */
    private function draftVendorEmail(Verification $verification): ?array
    {
        if (blank($verification->invoice_checks)) {
            return null;
        }

        $supplier = is_array($verification->invoice_data) ? ($verification->invoice_data['supplier_name'] ?? null) : null;

        return app(InvoiceNoticeService::class)->draft(
            accepted: $verification->approval === ApprovalDecision::Accepted,
            supplierName: is_string($supplier) ? $supplier : null,
            buyerName: OrganizationProfile::current()->entity_name,
            discrepancies: $this->discrepanciesFor($verification),
        );
    }

    /**
     * Every failed check, failed art. 150 finding, and triggered risk rule —
     * the raw material for the closing "comments" section.
     *
     * @return array<int, array<string, mixed>>
     */
    private function discrepanciesFor(Verification $verification): array
    {
        $items = [];

        foreach ($verification->checks ?? [] as $check) {
            if (($check['passed'] ?? true) === false) {
                $items[] = [
                    'kind' => 'check',
                    'label' => $check['label'] ?? '',
                    'article' => $check['article'] ?? '',
                    'detail' => $check['detail'] ?? '',
                    'action' => (string) __('app.remediation.check'),
                    'severity' => 'danger',
                ];
            }
        }

        foreach ($verification->invoice_checks ?? [] as $finding) {
            if (($finding['passed'] ?? true) === false) {
                $items[] = [
                    'kind' => 'conformity',
                    'label' => $finding['label'] ?? '',
                    'article' => 'CGI art. 150',
                    'detail' => filled($finding['detail'] ?? null)
                        ? (string) $finding['detail']
                        : (string) __('app.conformity.type_'.($finding['type'] ?? 'forme')),
                    'action' => (string) __('app.remediation.conformity'),
                    'severity' => 'danger',
                ];
            }
        }

        foreach ($verification->triggered_rules ?? [] as $rule) {
            $items[] = [
                'kind' => 'rule',
                'label' => $rule['title'] ?? '',
                'article' => $rule['article'] ?? '',
                'detail' => $rule['message'] ?? '',
                'action' => (string) __('app.remediation.rule'),
                'severity' => 'warning',
            ];
        }

        return $items;
    }

    private function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
