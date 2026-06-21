<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\ComplianceStats;
use App\Filament\App\Widgets\GettingStarted;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -30;

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.dashboard');
    }

    public function getTitle(): string
    {
        return (string) __('app.nav.dashboard');
    }

    /**
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            GettingStarted::class,
            ComplianceStats::class,
        ];
    }
}
