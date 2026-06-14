<?php

namespace App\Filament\App\Resources\Verifications\Pages;

use App\Domain\Acf\UnreadableAcfException;
use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Domain\Risk\PaymentMethod;
use App\Filament\App\Resources\Verifications\VerificationResource;
use App\Services\Acf\AcfIngestionService;
use App\Services\Verification\BatchVerificationService;
use App\Services\Verification\VerificationService;
use App\Services\Verification\VerifyRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListVerifications extends ListRecords
{
    protected static string $resource = VerificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->recordingAction(),
            $this->settlementAction(),
        ];
    }

    /**
     * Stage A — Invoice recording: one vendor, import the ACF (or type the NIU),
     * run the four checks at the recording stage.
     */
    private function recordingAction(): Action
    {
        return Action::make('record')
            ->label('Enregistrer une facture')
            ->icon('heroicon-o-document-plus')
            ->modalHeading('Enregistrement facture — un fournisseur')
            ->modalSubmitActionLabel('Vérifier')
            ->schema([
                FileUpload::make('acf_pdf')
                    ->label('Attestation de Conformité Fiscale (PDF)')
                    ->helperText('Importez l’ACF : le NIU et la référence en sont extraits automatiquement.')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10 * 1024)
                    ->disk('local')
                    ->visibility('private')
                    ->directory('acf')
                    ->storeFiles(true)
                    ->previewable(false),
                TextInput::make('raw_input')
                    ->label('NIU (si pas d’ACF)')
                    ->placeholder('P058712345678X')
                    ->extraInputAttributes(['style' => 'font-family: monospace; letter-spacing: 0.05em;'])
                    ->helperText('Laissez vide si vous importez une ACF ci-dessus.'),
                TextInput::make('invoice_amount_xaf')
                    ->label('Montant de la facture (FCFA)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('invoice_name')
                    ->label('Nom / raison sociale sur la facture'),
                Select::make('payment_method')
                    ->label('Mode de paiement')
                    ->options(collect(PaymentMethod::cases())
                        ->mapWithKeys(fn (PaymentMethod $m): array => [$m->value => $m->label()])
                        ->all()),
                Toggle::make('vat_charged')->label('TVA facturée'),
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
                        Notification::make()->title('ACF illisible')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    $acfReference = $certificate->reference;
                    $rawInput ??= $certificate->niu;
                }

                if (blank($rawInput)) {
                    Notification::make()
                        ->title('NIU manquant')
                        ->body('Importez une ACF ou saisissez un NIU.')
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
                $verification->approval === ApprovalDecision::Accepted ? $notification->success() : $notification->danger();
                $notification->send();

                return redirect(VerificationResource::getUrl('view', ['record' => $verification]));
            });
    }

    /**
     * Stage B — Invoice settlement: a batch of NIUs re-checked at payment time.
     */
    private function settlementAction(): Action
    {
        return Action::make('settle')
            ->label('Vérifier un lot (règlement)')
            ->icon('heroicon-o-queue-list')
            ->color('gray')
            ->modalHeading('Règlement facture — lot de NIU')
            ->modalSubmitActionLabel('Vérifier le lot')
            ->schema([
                Textarea::make('nius')
                    ->label('NIU à régler')
                    ->required()
                    ->rows(8)
                    ->extraInputAttributes(['style' => 'font-family: monospace;'])
                    ->helperText('Un NIU par ligne (ou séparés par des virgules).'),
            ])
            ->action(function (array $data) {
                $batch = app(BatchVerificationService::class);
                $nius = $batch->parse((string) $data['nius']);

                if ($nius === []) {
                    Notification::make()->title('Aucun NIU valide fourni')->warning()->send();

                    return;
                }

                $results = $batch->verifyBatch($nius, VerificationStage::Settlement, auth()->id());
                $summary = $batch->summarise($results);

                Notification::make()
                    ->title("Lot vérifié : {$summary['accepted']} acceptés / {$summary['rejected']} refusés")
                    ->body("{$summary['total']} NIU traités au règlement.")
                    ->status($summary['rejected'] > 0 ? 'warning' : 'success')
                    ->send();
            });
    }
}
