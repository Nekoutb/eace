<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Pages\FirmConsole\FirmSubAccountsWidget;
use App\Filament\App\Pages\FirmConsole\FirmTeamWidget;
use App\Models\User;
use App\Support\AccountSwitcher;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * Tenant app panel — "My firm": the accounting firm's own console, visible ONLY to
 * its power users. Create client sub-accounts (within the plan cap), invite team
 * members, and assign who works which account. Everything runs through
 * FirmMembershipService, so the tested rules (cap, cross-firm refusal, credential
 * mirroring) apply; the operator supervises the same data from /admin → Firms.
 */
class FirmConsole extends Page
{
    protected string $view = 'filament.app.pages.firm-console';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?int $navigationSort = -25;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && AccountSwitcher::managedFirm($user) !== null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('firm.console.nav');
    }

    public function getTitle(): string
    {
        $firm = AccountSwitcher::managedFirm(Filament::auth()->user());

        return $firm !== null ? (string) $firm->name : (string) __('firm.console.nav');
    }

    public function getSubheading(): ?string
    {
        return (string) __('firm.console.subheading');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FirmSubAccountsWidget::class,
            FirmTeamWidget::class,
        ];
    }
}
