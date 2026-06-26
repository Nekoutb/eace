<?php

namespace App\Filament\App\Pages;

use App\Models\OrganizationProfile;
use App\Support\Impersonation;
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

    /** The buyer identity governs invoice conformity for everyone — admins only. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public ?string $entityName = null;

    /** Accepted name variations, one per line (textarea-bound). */
    public ?string $entityNameAliases = null;

    public ?string $entityNiu = null;

    public ?string $entityRccm = null;

    public ?string $entityAddress = null;

    public function mount(): void
    {
        $profile = OrganizationProfile::current();
        $this->entityName = $profile->entity_name;
        $this->entityNameAliases = implode("\n", is_array($profile->entity_name_aliases) ? $profile->entity_name_aliases : []);
        $this->entityNiu = $profile->entity_niu;
        $this->entityRccm = $profile->entity_rccm;
        $this->entityAddress = $profile->entity_address;
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
        Impersonation::denyIfReadOnly();

        $this->validate([
            'entityName' => ['nullable', 'string', 'max:255'],
            'entityNameAliases' => ['nullable', 'string', 'max:2000'],
            'entityNiu' => ['nullable', 'string', 'max:32'],
            'entityRccm' => ['nullable', 'string', 'max:64'],
            'entityAddress' => ['nullable', 'string', 'max:255'],
        ]);

        // One accepted variation per line — trimmed, de-duplicated, blanks dropped.
        $aliases = collect(preg_split('/\r\n|\r|\n/', (string) $this->entityNameAliases) ?: [])
            ->map(static fn ($line): string => trim((string) $line))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $profile = OrganizationProfile::current();
        $profile->entity_name = filled($this->entityName) ? trim((string) $this->entityName) : null;
        $profile->entity_name_aliases = $aliases !== [] ? $aliases : null;
        $profile->entity_niu = filled($this->entityNiu) ? strtoupper(trim((string) $this->entityNiu)) : null;
        $profile->entity_rccm = filled($this->entityRccm) ? trim((string) $this->entityRccm) : null;
        $profile->entity_address = filled($this->entityAddress) ? trim((string) $this->entityAddress) : null;
        $profile->save();

        Notification::make()->title(__('app.ui.notif_settings_saved'))->success()->send();
    }
}
