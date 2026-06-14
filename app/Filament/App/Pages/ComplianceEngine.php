<?php

namespace App\Filament\App\Pages;

use App\Domain\Acf\UnreadableAcfException;
use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\CheckKey;
use App\Domain\Approval\VerificationStage;
use App\Domain\Invoice\InvoiceReader;
use App\Domain\Risk\PaymentMethod;
use App\Filament\App\Resources\Verifications\VerificationResource;
use App\Models\Verification;
use App\Services\Acf\AcfIngestionService;
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
 * between invoice **recording** (one vendor: ACF + scanned invoice) and invoice
 * **settlement** (a batch of NIUs). Both run the four strict checks (plus the
 * art. 150 conditions de forme et de fond at recording) and reveal every test's
 * output inline — a results table and a closing list of discrepancies — without
 * ever leaving the page.
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

    public ?string $paymentMethod = null;

    public bool $vatCharged = false;

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

    /** Run the checks for the current phase and reveal the results inline. */
    public function run(): void
    {
        $this->validate([
            'acfUpload' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'invoiceUpload' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
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
        $acfReference = null;
        $rawInput = filled($this->rawInput) ? trim((string) $this->rawInput) : null;

        if ($this->acfUpload instanceof TemporaryUploadedFile) {
            $path = $this->acfUpload->store('acf', 'local');

            if (! is_string($path)) {
                Notification::make()->title(__('app.ui.notif_acf_unreadable'))->danger()->send();

                return;
            }

            try {
                $certificate = app(AcfIngestionService::class)->ingestFromUpload(
                    diskPath: $path,
                    disk: 'local',
                    uploadedBy: auth()->id(),
                );
            } catch (UnreadableAcfException $e) {
                Notification::make()->title(__('app.ui.notif_acf_unreadable'))->body($e->getMessage())->danger()->send();

                return;
            }

            $acfReference = $certificate->reference;
            $rawInput ??= $certificate->niu;
        }

        if (blank($rawInput)) {
            Notification::make()
                ->title(__('app.ui.notif_niu_missing'))
                ->body(__('app.ui.notif_niu_missing_body'))
                ->warning()
                ->send();

            return;
        }

        $invoiceData = null;
        if ($this->invoiceUpload instanceof TemporaryUploadedFile) {
            $path = $this->invoiceUpload->store('invoices', 'local');

            if (! is_string($path)) {
                Notification::make()->title(__('app.ui.notif_invoice_unreadable'))->danger()->send();

                return;
            }

            try {
                $invoiceData = app(InvoiceReader::class)->read($path, 'local');
            } catch (\Throwable $e) {
                Notification::make()->title(__('app.ui.notif_invoice_unreadable'))->body($e->getMessage())->danger()->send();

                return;
            }
        }

        $verification = app(VerificationService::class)->verify(new VerifyRequest(
            rawInput: $rawInput,
            stage: VerificationStage::Recording,
            acfReference: $acfReference,
            invoiceAmountXaf: filled($this->invoiceAmount) ? (int) $this->invoiceAmount : null,
            invoiceName: filled($this->invoiceName) ? (string) $this->invoiceName : null,
            vatCharged: $this->vatCharged,
            paymentMethod: filled($this->paymentMethod) ? PaymentMethod::from((string) $this->paymentMethod) : null,
            invoiceData: $invoiceData,
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
            'view_url' => VerificationResource::getUrl('view', ['record' => $verification]),
        ];
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
                    'detail' => (string) __('app.conformity.type_'.($finding['type'] ?? 'forme')),
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
                'severity' => 'warning',
            ];
        }

        return $items;
    }
}
