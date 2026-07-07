<?php

namespace App\Filament\Resources\Firms;

use App\Filament\Resources\Firms\Pages\ListFirms;
use App\Filament\Resources\Firms\Tables\FirmsTable;
use App\Models\Firm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Central admin. Accounting firms — the umbrella accounts: each owns client
 * sub-accounts (visible in Clients, tagged with the firm) and team members who
 * work them. Firms self-serve their sub-accounts from their own console; the
 * operator supervises here: plan caps, deactivation (suspends every sub-account),
 * and oversight of members. Operators (landlord) only.
 */
class FirmResource extends Resource
{
    protected static ?string $model = Firm::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?int $navigationSort = -9;

    public static function canAccess(): bool
    {
        return (bool) config('niu.multi_client', false)
            && auth()->user()?->isCentralUser() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('niu.multi_client', false);
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('firm.admin.nav');
    }

    public static function getModelLabel(): string
    {
        return (string) __('firm.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('firm.admin.nav');
    }

    public static function table(Table $table): Table
    {
        return FirmsTable::configure($table);
    }

    /** Firms are created by self-service signup (or the operator CLI), not a blank form. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFirms::route('/'),
        ];
    }
}
