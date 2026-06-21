<?php

namespace App\Filament\Resources\SupportStaff;

use App\Filament\Resources\SupportStaff\Pages\ListSupportStaff;
use App\Filament\Resources\SupportStaff\Tables\SupportStaffTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The operator's customer-service staff directory: onboard support agents and
 * supervisors (central users, 2FA-protected), see who is active. Only owners and
 * supervisors may reach it; agents handle conversations but not the team.
 */
class SupportStaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = -4;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageStaff() === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return (string) __('support.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('support.nav.staff');
    }

    public static function getModelLabel(): string
    {
        return (string) __('support.staff.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('support.staff.plural');
    }

    /** Central support staff only (every central user carries a staff_role). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('staff_role');
    }

    public static function canCreate(): bool
    {
        return false; // onboarding happens via the header action (temp password + email)
    }

    public static function table(Table $table): Table
    {
        return SupportStaffTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportStaff::route('/'),
        ];
    }
}
