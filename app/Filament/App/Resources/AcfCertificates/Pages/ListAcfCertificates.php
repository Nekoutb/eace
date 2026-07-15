<?php

namespace App\Filament\App\Resources\AcfCertificates\Pages;

use App\Domain\Acf\SupersededAcfException;
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
                ->label(__('app.ui.import_acf'))
                ->icon('heroicon-o-arrow-up-tray')
                ->modalSubmitActionLabel(__('app.ui.import'))
                ->schema([
                    FileUpload::make('acf_pdf')
                        ->label(__('app.ui.acf_pdf'))
                        ->required()
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(10 * 1024)
                        ->disk('local')
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
                            ->title(__('app.ui.notif_import_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    } catch (SupersededAcfException $e) {
                        // The register already holds a certificate running as
                        // long or longer for this vendor — upload not applied.
                        Notification::make()
                            ->title(__('app.ui.notif_acf_superseded'))
                            ->body($e->getMessage())
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('app.ui.notif_acf_imported', ['status' => $certificate->status->label()]))
                        ->body(__('app.ui.notif_acf_detail', [
                            'reference' => $certificate->reference,
                            'date' => $certificate->expires_at->format('d/m/Y'),
                        ]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
