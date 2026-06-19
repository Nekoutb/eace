<?php

namespace App\Filament\App\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * A plain-language guide: what each user does, step by step, and what output to
 * expect. Available to every role (it explains the recording and settlement work
 * as well as where to find evidence and attestations).
 */
class UserGuide extends Page
{
    protected string $view = 'filament.app.pages.user-guide';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.user_guide');
    }

    public function getTitle(): string
    {
        return (string) __('app.nav.user_guide');
    }
}
