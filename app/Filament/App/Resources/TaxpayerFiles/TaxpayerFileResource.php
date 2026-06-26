<?php

namespace App\Filament\App\Resources\TaxpayerFiles;

use App\Filament\App\Resources\TaxpayerFiles\Pages\ListTaxpayerFiles;
use App\Filament\App\Resources\TaxpayerFiles\Tables\TaxpayerFilesTable;
use App\Models\TaxpayerFileExtraction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The taxpayer-file download log — every weekly extraction of the DGI "fichier
 * des contribuables" (the Excel of all taxpayers), stored with a date/time
 * stamp, the row count, and the file's SHA-256. Read-only. Shared (central) —
 * the same source backs every tenant's NIU-existence check.
 */
class TaxpayerFileResource extends Resource
{
    protected static ?string $model = TaxpayerFileExtraction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?int $navigationSort = 6;

    /**
     * Client-portal resource: only a tenant (client) user may reach it. A platform
     * (central) user is barred here too — defence in depth beyond the /app panel
     * boundary, and a self-documenting statement of audience.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isCentralUser() === false;
    }

    /** Reached from the Audit Logs hub, not the top-level sidebar. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.taxpayer_files');
    }

    public static function getModelLabel(): string
    {
        return (string) __('app.nav.taxpayer_files');
    }

    public static function table(Table $table): Table
    {
        return TaxpayerFilesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxpayerFiles::route('/'),
        ];
    }
}
