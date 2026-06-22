<?php

namespace App\Filament\Resources\DataRequests;

use App\Filament\Resources\DataRequests\Pages\ListDataRequests;
use App\Filament\Resources\DataRequests\Tables\DataRequestsTable;
use App\Models\DataRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Data-subject request register (Loi 2024/017 art. 37–46). Requests are raised by
 * customers from the in-app "My data & privacy" page; central staff track them to
 * closure within the statutory window. Central staff only.
 */
class DataRequestResource extends Resource
{
    protected static ?string $model = DataRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 3;

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
        return (string) __('dp.nav.requests');
    }

    public static function getModelLabel(): string
    {
        return (string) __('dp.nav.requests');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('dp.nav.requests');
    }

    public static function getNavigationBadge(): ?string
    {
        $new = DataRequest::query()->where('status', 'new')->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return DataRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDataRequests::route('/'),
        ];
    }
}
