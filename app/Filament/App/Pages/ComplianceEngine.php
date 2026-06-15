<?php

namespace App\Filament\App\Pages;

use App\Domain\Acf\UnreadableAcfException;
use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\CheckKey;
use App\Domain\Approval\VerificationStage;
use App\Domain\Invoice\InvoiceData;
use App\Domain\Invoice\InvoiceReader;
use App\Filament\App\Resources\Verifications\VerificationResource;
use App\Models\OrganizationProfile;
use App\Models\Verification;
use App\Services\Acf\AcfIngestionService;
use App\Services\Invoice\InvoiceNoticeService;
use App\Services\Verification\BatchVerificationService;
use App\Services\Verification\VerificationService;
use App\Services\Verification\VerifyRequest;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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

    protected static ?int $navigationSort = -2;

    /** recording | settlement — the live phase toggle. */
    public string $stage = 'recording';

    // Recording inputs.
    /** @var TemporaryUploadedFile|null */
    public $acfUpload = null;

    /** @var TemporaryUploadedFile|null */
    public $invoiceUpload = null;

    public ?string $rawInput = null;

    public ?string $invoiceAmount = null;

    public ?string $invoiceName = null;

    // Captured as the uploads are read (auto-populate).
    public ?string $acfReference = null;

    public ?string $invoiceStoredPath = null;

    /** @var array<string, mixed>|null the scanned invoice, cached so run() needs no second OCR */
    public ?array $invoiceArray = null;

    /** @var array<string, mixed>|null human-readable summary of what the scan extracted */
    public ?array $extracted = null;

    // Settlement input.
    public ?string $nius = null;

    // Results (plain serialisable arrays — rendered inline).
    /** @var array<string, mixed>|null */
    public ?array $result = null;

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

    /** Switch phase and clear any results so the screen never mixes the two. */
    public function setStage(string $stage): void
    {
        $this->stage = $stage === 'settlement' ? 'settlement' : 'recording';
        $this->reset(['result', 'batch', 'discrepancies']);
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
    }

    /**
     * The invoice was selected — OCR it now and auto-populate the control fields
     * (supplier NIU, amount, name). The full extraction is cached for run().
     */
    public function updatedInvoiceUpload(): void
    {
        if (! $this->invoiceUpload instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validate(['invoiceUpload' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);

        $path = $this->invoiceUpload->store('invoices', 'local');
        if (! is_string($path)) {
            Notification::make()->title(__('app.ui.notif_invoice_unreadable'))->danger()->send();

            return;
        }

        try {
            $invoice = app(InvoiceReader::class)->read($path, 'local');
        } catch (\Throwable $e) {
            Notification::make()->title(__('app.ui.notif_invoice_unreadable'))->body($e->getMessage())->danger()->send();

            return;
        }

        $this->invoiceStoredPath = $path;
        $this->invoiceArray = $invoice->toArray();
        $this->extracted = $this->extractedSummary($invoice);

        if (blank($this->rawInput) && $invoice->supplierNiu !== null) {
            $this->rawInput = $invoice->supplierNiu->value;
        }
        if (blank($this->invoiceAmount) && $invoice->ttcAmount !== null) {
            $this->invoiceAmount = (string) $invoice->ttcAmount;
        }
        if (blank($this->invoiceName) && $this->filled($invoice->supplierName)) {
            $this->invoiceName = $invoice->supplierName;
        }

        Notification::make()->title(__('app.ui.notif_invoice_scanned'))->success()->send();
    }

    /** Run the checks for the current phase and reveal the results inline. */
    public function run(): void
    {
        $this->validate([
            'rawInput' => ['nullable', 'string', 'max:32'],
            'invoiceAmount' => ['nullable', 'numeric', 'min:0'],
            'invoiceName' => ['nullable', 'string', 'max:255'],
            'nius' => $this->stage === 'settlement' ? ['required', 'string'] : ['nullable', 'string'],
        ]);

        $this->reset(['result', 'batch', 'discrepancies']);

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

        $invoiceData = is_array($this->invoiceArray) && $this->invoiceArray !== []
            ? InvoiceData::fromArray($this->invoiceArray)
            : null;

        $verification = app(VerificationService::class)->verify(new VerifyRequest(
            rawInput: $rawInput,
            stage: VerificationStage::Recording,
            acfReference: $this->acfReference,
            invoiceAmountXaf: filled($this->invoiceAmount) ? (int) $this->invoiceAmount : null,
            invoiceName: filled($this->invoiceName) ? (string) $this->invoiceName : null,
            invoiceData: $invoiceData,
            invoiceFilePath: $this->invoiceStoredPath,
            checkedBy: auth()->id(),
        ));

        $this->result = $this->present($verification);
        $this->discrepancies = $this->discrepanciesFor($verification);

        $notification = Notification::make()->title($verification->approval?->label() ?? $verification->verdict->label());
        $verification->approval === ApprovalDecision::Accepted
            ? $notification->success()
            : $notification->danger();
        $notification->send();
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

        return [
            'niu' => $verification->niu,
            'raw_input' => $verification->raw_input,
            'verdict' => [
                'label' => $verification->verdict->label(),
                'color' => $verification->verdict->color(),
                'icon' => $verification->verdict->icon(),
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
            'triggered_rules' => $verification->triggered_rules ?? [],
            'vendor_email' => $this->draftVendorEmail($verification),
            'view_url' => VerificationResource::getUrl('view', ['record' => $verification]),
        ];
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
