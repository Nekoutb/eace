<?php

namespace App\Filament\Resources\DataAccessLogs;

use App\Filament\Resources\DataAccessLogs\Pages\ListDataAccessLogs;
use App\Filament\Resources\DataAccessLogs\Tables\DataAccessLogsTable;
use App\Models\DataAccessLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Read-level access log (Loi 2024/017 art. 27): an a-posteriori trail of who
 * exported or downloaded personal data. Read-only; central staff only.
 */
class DataAccessLogResource extends Resource
{
    protected static ?string $model = DataAccessLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static ?int $navigationSort = 5;

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
        return (string) __('dp.nav.access_log');
    }

    public static function getModelLabel(): string
    {
        return (string) __('dp.nav.access_log');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('dp.nav.access_log');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return DataAccessLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDataAccessLogs::route('/'),
        ];
    }
}
