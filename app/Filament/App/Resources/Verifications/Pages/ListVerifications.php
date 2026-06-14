<?php

namespace App\Filament\App\Resources\Verifications\Pages;

use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Domain\Risk\PaymentMethod;
use App\Filament\App\Resources\Verifications\VerificationResource;
use App\Services\Verification\VerificationService;
use App\Services\Verification\VerifyRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
            Action::make('verify')
                ->label('Vérifier un NIU')
                ->icon('heroicon-o-magnifying-glass')
                ->modalSubmitActionLabel('Vérifier')
                ->schema([
                    Select::make('stage')
                        ->label('Étape')
                        ->options(collect(VerificationStage::cases())
                            ->mapWithKeys(fn (VerificationStage $s): array => [$s->value => $s->label()])
                            ->all())
                        ->default(VerificationStage::Recording->value)
                        ->required(),
                    TextInput::make('raw_input')
                        ->label('NIU à vérifier')
                        ->placeholder('P058712345678X')
                        ->required()
                        ->extraInputAttributes(['style' => 'font-family: monospace; letter-spacing: 0.05em;'])
                        ->helperText('14 caractères : P (personne physique) ou M (personne morale) + 12 chiffres + lettre.'),
                    TextInput::make('acf_reference')
                        ->label('Référence ACF (optionnel)')
                        ->placeholder('620264471967')
                        ->helperText('Sinon, l’ACF la plus récente du NIU est utilisée.'),
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
                    Toggle::make('vat_charged')
                        ->label('TVA facturée'),
                ])
                ->action(function (array $data) {
                    $verification = app(VerificationService::class)->verify(new VerifyRequest(
                        rawInput: (string) $data['raw_input'],
                        stage: VerificationStage::from($data['stage'] ?? VerificationStage::Recording->value),
                        acfReference: filled($data['acf_reference'] ?? null)
                            ? (string) $data['acf_reference']
                            : null,
                        invoiceAmountXaf: filled($data['invoice_amount_xaf'] ?? null)
                            ? (int) $data['invoice_amount_xaf']
                            : null,
                        invoiceName: filled($data['invoice_name'] ?? null)
                            ? (string) $data['invoice_name']
                            : null,
                        vatCharged: (bool) ($data['vat_charged'] ?? false),
                        paymentMethod: filled($data['payment_method'] ?? null)
                            ? PaymentMethod::from($data['payment_method'])
                            : null,
                        checkedBy: auth()->id(),
                    ));

                    $notification = Notification::make()
                        ->title($verification->approval?->label() ?? $verification->verdict->label())
                        ->body('Verdict : '.$verification->verdict->label());

                    $verification->approval === ApprovalDecision::Accepted
                        ? $notification->success()
                        : $notification->danger();

                    $notification->send();

                    return redirect(VerificationResource::getUrl('view', ['record' => $verification]));
                }),
        ];
    }
}
