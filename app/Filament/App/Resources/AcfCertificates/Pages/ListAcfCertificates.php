<?php

namespace App\Filament\App\Resources\AcfCertificates\Pages;

use App\Domain\Acf\UnreadableAcfException;
use App\Filament\App\Resources\AcfCertificates\AcfCertificateResource;
use App\Services\Acf\AcfIngestionService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAcfCertificates extends ListRecords
{
    protected static string $resource = AcfCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ingestAcf')
                ->label('Importer une ACF')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalSubmitActionLabel('Importer')
                ->schema([
                    FileUpload::make('acf_pdf')
                        ->label('Attestation de Conformité Fiscale (PDF)')
                        ->required()
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10 * 1024) // 10 MB
                        ->disk('local')        // private, tenant-suffixed by the filesystem bootstrapper
                        ->visibility('private')
                        ->directory('acf')
                        ->storeFiles(true)
                        ->previewable(false),
                ])
                ->action(function (array $data): void {
                    try {
                        $certificate = app(AcfIngestionService::class)->ingestFromUpload(
                            diskPath: (string) $data['acf_pdf'],
                            disk: 'local',
                            uploadedBy: auth()->id(),
                        );
                    } catch (UnreadableAcfException $e) {
                        Notification::make()
                            ->title('Import échoué')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('ACF importée — '.$certificate->status->label())
                        ->body('Réf. '.$certificate->reference.' · valide jusqu’au '.$certificate->expires_at->format('d/m/Y'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
