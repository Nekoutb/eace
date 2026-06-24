<?php

namespace App\Filament\Resources\AcademyUsers;

use App\Filament\Resources\AcademyUsers\Pages\ListAcademyUsers;
use App\Filament\Resources\AcademyUsers\Tables\AcademyUsersTable;
use App\Models\AcademyUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Central admin panel. Manage who can sign in to the Elite Advisors Academy (sales
 * training) at /academy. An academy account reaches the training module ONLY — it
 * is a separate `academy` guard, never the admin console or a workspace, and signs
 * in with a simple email + password (no two-factor). Operators (landlord) only.
 */
class AcademyUserResource extends Resource
{
    protected static ?string $model = AcademyUser::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 15;

    public static function canAccess(): bool
    {
        return auth()->user()?->isCentralUser() === true;
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('academy.nav');
    }

    public static function getModelLabel(): string
    {
        return (string) __('academy.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('academy.plural');
    }

    public static function table(Table $table): Table
    {
        return AcademyUsersTable::configure($table);
    }

    /** Accounts are created through the guided "Add" action, not a blank form. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcademyUsers::route('/'),
        ];
    }
}
