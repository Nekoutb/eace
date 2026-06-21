<?php

namespace App\Filament\App\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * A signed-in view of how the platform protects a client's fiscal data — the
 * security stack as layers of protection (encryption, WORM immutability, trusted
 * timestamps, workspace isolation, audited hosting) with a discreet strip of the
 * top certifications the underpinning infrastructure carries. Available to every
 * role; mirrors the public /security page but kept data-centric (no vendor names).
 */
class Security extends Page
{
    protected string $view = 'filament.app.pages.security';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.security');
    }

    public function getTitle(): string
    {
        return (string) __('app.nav.security');
    }
}
