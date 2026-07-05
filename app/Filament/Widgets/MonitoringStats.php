<?php

namespace App\Filament\Widgets;

use App\Models\MonitoredService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * At-a-glance operations health on the admin dashboard: how many services are
 * monitored, renewing soon, overdue, or with a down site. Operators only.
 */
class MonitoringStats extends StatsOverviewWidget
{
    protected static ?int $sort = 8;

    public static function canView(): bool
    {
        return auth()->user()?->isCentralUser() === true;
    }

    protected function getStats(): array
    {
        $services = MonitoredService::query()->where('is_active', true)->get();

        $overdue = $services->filter(fn (MonitoredService $s): bool => $s->renewalStatus() === 'overdue')->count();
        $dueSoon = $services->filter(fn (MonitoredService $s): bool => $s->renewalStatus() === 'due_soon')->count();
        $down = $services->filter(fn (MonitoredService $s): bool => $s->last_status === 'down')->count();

        return [
            Stat::make((string) __('monitoring.stat_services'), (string) $services->count())
                ->description((string) __('monitoring.stat_services_desc'))
                ->color('gray'),
            Stat::make((string) __('monitoring.stat_due'), (string) $dueSoon)
                ->color($dueSoon > 0 ? 'warning' : 'success'),
            Stat::make((string) __('monitoring.stat_overdue'), (string) $overdue)
                ->color($overdue > 0 ? 'danger' : 'success'),
            Stat::make((string) __('monitoring.stat_down'), (string) $down)
                ->color($down > 0 ? 'danger' : 'success'),
        ];
    }
}
