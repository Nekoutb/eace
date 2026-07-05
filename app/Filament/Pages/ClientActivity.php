<?php

namespace App\Filament\Pages;

use App\Filament\Pages\ClientActivity\ActivityChart;
use App\Filament\Pages\ClientActivity\ClientActivityTable;
use BackedEnum;
use Filament\Pages\Page;

/**
 * Central admin. "Client activity" — how intensively each client uses the platform:
 * a daily graph of invoices recorded, payments checked and ACF certificates
 * processed, plus a per-client outreach table (30-day volumes and last activity).
 * Built for proactive account care: a client going quiet shows up here first.
 * Operators (landlord) only.
 */
class ClientActivity extends Page
{
    protected string $view = 'filament.pages.client-activity';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?int $navigationSort = 19;

    public static function canAccess(): bool
    {
        return auth()->user()?->isCentralUser() === true;
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('activity.nav');
    }

    public function getTitle(): string
    {
        return (string) __('activity.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('activity.subheading');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ActivityChart::class,
            ClientActivityTable::class,
        ];
    }
}
