<?php

namespace App\Filament\App\Pages;

use App\Domain\Acf\UnreadableAcfException;
use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Domain\Risk\PaymentMethod;
use App\Filament\App\Resources\Verifications\VerificationResource;
use App\Services\Acf\AcfIngestionService;
use App\Services\Verification\BatchVerificationService;
use App\Services\Verification\VerificationService;
use App\Services\Verification\VerifyRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * The Compliance Engine — the entry point. The user first picks the verification
 * phase (invoice recording vs invoice settlement); each card opens the matching
 * flow, which runs the four-check strict approval at that stage.
 */
class ComplianceEngine extends Page
{
    protected string $view = 'filament.app.pages.compliance-engine';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = -2;

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.compliance_engine');
    }

    public function getTitle(): string
    {
        return (string) __('app.nav.compliance_engine');
    }

    /**
     * Phase A — invoice recording: one vendor, import the ACF (or type the NIU),
     * run the four checks at the recording stage.
     */
    public function recordAction(): Action
    {
        return Action::make('record')
            ->label(__('app.ui.record_invoice'))
            ->icon('heroicon-o-document-plus')
            ->modalHeading(__('app.ui.record_invoice'))
            ->modalSubmitActionLabel(__('app.ui.verify'))
            ->schema([
                FileUpload::make('acf_pdf')
                    ->label(__('app.ui.acf_pdf'))
                    ->helperText(__('app.ui.acf_help'))
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10 * 1024)
                    ->disk('local')
                    ->visibility('private')
                    ->directory('acf')
                    ->storeFiles(true)
                    ->previewable(false),
                TextInput::make('raw_input')
                    ->label(__('app.ui.niu_if_no_acf'))
                    ->placeholder('P058712345678X')
                    ->extraInputAttributes(['style' => 'font-family: monospace; letter-spacing: 0.05em;'])
                    ->helperText(__('app.ui.niu_if_no_acf_help')),
                TextInput::make('invoice_amount_xaf')
                    ->label(__('app.ui.invoice_amount'))
                    ->numeric()
                    ->minValue(0),
                TextInput::make('invoice_name')
                    ->label(__('app.ui.invoice_name')),
                Select::make('payment_method')
                    ->label(__('app.ui.payment_method'))
                    ->options(collect(PaymentMethod::cases())
                        ->mapWithKeys(fn (PaymentMethod $m): array => [$m->value => $m->label()])
                        ->all()),
                Toggle::make('vat_charged')->label(__('app.ui.vat_charged')),
            ])
            ->action(function (array $data) {
                $acfReference = null;
                $rawInput = filled($data['raw_input'] ?? null) ? (string) $data['raw_input'] : null;

                if (filled($data['acf_pdf'] ?? null)) {
                    try {
                        $certificate = app(AcfIngestionService::class)->ingestFromUpload(
                            diskPath: (string) $data['acf_pdf'],
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

                $verification = app(VerificationService::class)->verify(new VerifyRequest(
                    rawInput: $rawInput,
                    stage: VerificationStage::Recording,
                    acfReference: $acfReference,
                    invoiceAmountXaf: filled($data['invoice_amount_xaf'] ?? null) ? (int) $data['invoice_amount_xaf'] : null,
                    invoiceName: filled($data['invoice_name'] ?? null) ? (string) $data['invoice_name'] : null,
                    vatCharged: (bool) ($data['vat_charged'] ?? false),
                    paymentMethod: filled($data['payment_method'] ?? null) ? PaymentMethod::from($data['payment_method']) : null,
                    checkedBy: auth()->id(),
                ));

                $notification = Notification::make()->title($verification->approval?->label() ?? $verification->verdict->label());
                $verification->approval === ApprovalDecision::Accepted
                    ? $notification->success()
                    : $notification->danger();
                $notification->send();

                return redirect(VerificationResource::getUrl('view', ['record' => $verification]));
            });
    }

    /**
     * Phase B — invoice settlement: a batch of NIUs re-checked at payment time.
     */
    public function settleAction(): Action
    {
        return Action::make('settle')
            ->label(__('app.ui.settle_batch'))
            ->icon('heroicon-o-queue-list')
            ->color('gray')
            ->modalHeading(__('app.ui.settle_batch'))
            ->modalSubmitActionLabel(__('app.ui.verify_batch'))
            ->schema([
                Textarea::make('nius')
                    ->label(__('app.ui.niu_batch'))
                    ->required()
                    ->rows(8)
                    ->extraInputAttributes(['style' => 'font-family: monospace;'])
                    ->helperText(__('app.ui.batch_help')),
            ])
            ->action(function (array $data) {
                $batch = app(BatchVerificationService::class);
                $nius = $batch->parse((string) $data['nius']);

                if ($nius === []) {
                    Notification::make()->title(__('app.ui.notif_no_niu'))->warning()->send();

                    return;
                }

                $results = $batch->verifyBatch($nius, VerificationStage::Settlement, auth()->id());
                $summary = $batch->summarise($results);

                Notification::make()
                    ->title(__('app.ui.notif_batch_done', ['accepted' => $summary['accepted'], 'rejected' => $summary['rejected']]))
                    ->body(__('app.ui.notif_batch_body', ['total' => $summary['total']]))
                    ->status($summary['rejected'] > 0 ? 'warning' : 'success')
                    ->send();
            });
    }
}
