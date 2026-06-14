<?php

namespace App\Filament\App\Pages;

use App\Models\OrganizationProfile;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * The organisation's own identity as the buyer ("entity receiving the invoice").
 * The name + NIU saved here are what every scanned supplier invoice is checked
 * against — the client mentions of CGI art. 150.
 */
class Settings extends Page
{
    protected string $view = 'filament.app.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 10;

    public ?string $entityName = null;

    public ?string $entityNiu = null;

    public function mount(): void
    {
        $profile = OrganizationProfile::current();
        $this->entityName = $profile->entity_name;
        $this->entityNiu = $profile->entity_niu;
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.settings');
    }

    public function getTitle(): string
    {
        return (string) __('app.nav.settings');
    }

    public function save(): void
    {
        $this->validate([
            'entityName' => ['nullable', 'string', 'max:255'],
            'entityNiu' => ['nullable', 'string', 'max:32'],
        ]);

        $profile = OrganizationProfile::current();
        $profile->entity_name = filled($this->entityName) ? trim((string) $this->entityName) : null;
        $profile->entity_niu = filled($this->entityNiu) ? strtoupper(trim((string) $this->entityNiu)) : null;
        $profile->save();

        Notification::make()->title(__('app.ui.notif_settings_saved'))->success()->send();
    }
}
