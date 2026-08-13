<?php

namespace App\Filament\App\Resources\Verifications\Pages;

use App\Domain\Approval\ApprovalDecision;
use App\Filament\App\Resources\Verifications\VerificationResource;
use App\Models\Verification;
use App\Support\Impersonation;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewVerification extends ViewRecord
{
    protected static string $resource = VerificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->overrideAction(),
        ];
    }

    /**
     * Record an operator's justification for proceeding despite the system's
     * rejection. The machine verdict is left untouched — this is an additive
     * audit note (who proceeded, and why). Only offered on a rejected record.
     */
    private function overrideAction(): Action
    {
        return Action::make('override')
            ->label(fn (Verification $record): string => $record->isOverridden()
                ? (string) __('app.verification.override_edit')
                : (string) __('app.verification.override'))
            ->icon('heroicon-o-hand-raised')
            ->color('warning')
            ->visible(fn (Verification $record): bool => $record->approval === ApprovalDecision::Rejected)
            ->modalHeading(__('app.verification.override_heading'))
            ->modalDescription(__('app.verification.override_desc'))
            ->modalSubmitActionLabel(__('app.verification.override_submit'))
            ->fillForm(fn (Verification $record): array => ['reason' => $record->override_reason])
            ->schema([
                Textarea::make('reason')
                    ->label(__('app.verification.override_reason_label'))
                    ->placeholder(__('app.verification.override_reason_ph'))
                    ->required()
                    ->rows(4)
                    ->maxLength(2000),
            ])
            ->action(function (Verification $record, array $data): void {
                Impersonation::denyIfReadOnly();

                $record->update([
                    'override_reason' => trim((string) $data['reason']),
                    'overridden_by' => auth()->id(),
                    'overridden_at' => now(),
                ]);

                Notification::make()->title(__('app.verification.override_saved'))->success()->send();
            });
    }
}
