<?php

namespace App\Filament\Resources\MonitoredServices\Pages;

use App\Filament\Resources\MonitoredServices\MonitoredServiceResource;
use App\Models\MonitoredService;
use App\Services\Monitoring\UptimeChecker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMonitoredServices extends ListRecords
{
    protected static string $resource = MonitoredServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')
                ->label(__('monitoring.add'))
                ->icon('heroicon-o-plus-circle')
                ->modalHeading(__('monitoring.add'))
                ->modalDescription(__('monitoring.add_desc'))
                ->modalSubmitActionLabel(__('monitoring.add'))
                ->schema(MonitoredServiceResource::formSchema())
                ->action(fn (array $data) => MonitoredService::query()->create($data)),

            Action::make('checkNow')
                ->label(__('monitoring.check_now'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $checker = app(UptimeChecker::class);
                    $count = 0;
                    foreach (MonitoredService::query()->where('is_active', true)->whereNotNull('health_url')->get() as $service) {
                        $checker->check($service);
                        $count++;
                    }

                    Notification::make()->title(__('monitoring.checked', ['n' => $count]))->success()->send();
                }),
        ];
    }
}
