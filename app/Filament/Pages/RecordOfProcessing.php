<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * The Record of Processing Activities (Loi 2024/017 art. 29), rendered from
 * config/ropa.php. Central staff only.
 */
class RecordOfProcessing extends Page
{
    protected string $view = 'filament.pages.record-of-processing';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->isCentralUser() === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return (string) __('dp.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('dp.nav.ropa');
    }

    public function getTitle(): string
    {
        return (string) __('dp.ropa.title');
    }

    /**
     * The processing activities, localised to the current language.
     *
     * @return list<array{name: string, purpose: string, basis: string, subjects: string, data: string, recipients: string, transfers: string, retention: string}>
     */
    public function activities(): array
    {
        $loc = str_starts_with(app()->getLocale(), 'fr') ? 'fr' : 'en';
        $out = [];

        foreach ((array) config('ropa.activities', []) as $activity) {
            if (! is_array($activity)) {
                continue;
            }

            $out[] = [
                'name' => $this->loc($activity['name'] ?? '', $loc),
                'purpose' => $this->loc($activity['purpose'] ?? '', $loc),
                'basis' => $this->loc($activity['basis'] ?? '', $loc),
                'subjects' => $this->loc($activity['subjects'] ?? '', $loc),
                'data' => $this->loc($activity['data'] ?? '', $loc),
                'recipients' => $this->loc($activity['recipients'] ?? '', $loc),
                'transfers' => $this->loc($activity['transfers'] ?? '', $loc),
                'retention' => $this->loc($activity['retention'] ?? '', $loc),
            ];
        }

        return $out;
    }

    public function controllerName(): string
    {
        return (string) config('niu.data_protection.controller_name', 'Elite Advisors');
    }

    public function authorityReference(): string
    {
        return (string) config('niu.data_protection.authority_reference', '');
    }

    private function loc(mixed $value, string $loc): string
    {
        if (is_array($value)) {
            $resolved = $value[$loc] ?? $value['en'] ?? '';

            return is_string($resolved) ? $resolved : '';
        }

        return is_string($value) ? $value : '';
    }
}
