<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Tables\ClientsTable;
use App\Models\Tenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Central admin panel. A "client" is a tenant — provisioning one creates its
 * isolated database, subdomain, and first admin user (see ClientProvisioningService).
 */
class ClientResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = -10;

    /** Defence in depth: only central (landlord) users may manage clients. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isCentralUser() === true;
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.clients');
    }

    public static function getModelLabel(): string
    {
        return (string) __('app.client.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('app.nav.clients');
    }

    public static function table(Table $table): Table
    {
        return ClientsTable::configure($table);
    }

    /** Clients are created through the guided "Provision" action, not a blank form. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
        ];
    }
}
