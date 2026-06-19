<?php

namespace App\Filament\App\Resources\TaxpayerFiles\Pages;

use App\Filament\App\Resources\TaxpayerFiles\TaxpayerFileResource;
use App\Jobs\RefreshTaxpayerFile;
use App\Models\TaxpayerFileExtraction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTaxpayerFiles extends ListRecords
{
    protected static string $resource = TaxpayerFileResource::class;

    public function getSubheading(): ?string
    {
        $current = TaxpayerFileExtraction::current();

        if ($current === null) {
            // No register on record yet — tell the user (and any newly onboarded
            // workspace) they can pull the latest one so NIU controls can run.
            return (string) __('app.audit.taxpayer_files_none');
        }

        return (string) __('app.audit.taxpayer_files_current', [
            'period' => $current->period_label,
            'count' => number_format($current->row_count),
            'when' => $current->extracted_at?->translatedFormat('d M Y, H:i') ?? '—',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('updateRegister')
                ->label(__('app.audit.taxpayer_update'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                // Admins only: it triggers a shared, central download for everyone.
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->requiresConfirmation()
                ->modalHeading(__('app.audit.taxpayer_update'))
                ->modalDescription(__('app.audit.taxpayer_update_desc'))
                ->modalSubmitActionLabel(__('app.audit.taxpayer_update_confirm'))
                ->action(function (): void {
                    RefreshTaxpayerFile::dispatch();

                    Notification::make()
                        ->title(__('app.audit.taxpayer_update_queued_title'))
                        ->body(__('app.audit.taxpayer_update_queued_body'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
