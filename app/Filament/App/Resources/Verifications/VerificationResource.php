<?php

namespace App\Filament\App\Resources\Verifications;

use App\Filament\App\Resources\Verifications\Pages\ListVerifications;
use App\Filament\App\Resources\Verifications\Pages\ViewVerification;
use App\Filament\App\Resources\Verifications\Schemas\VerificationInfolist;
use App\Filament\App\Resources\Verifications\Tables\VerificationsTable;
use App\Models\Verification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VerificationResource extends Resource
{
    protected static ?string $model = Verification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'niu';

    /** Reached from the Audit Logs hub, not the top-level sidebar. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.third_parties_evidence');
    }

    public static function getModelLabel(): string
    {
        return (string) __('app.nav.third_parties_evidence');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('app.nav.third_parties_evidence');
    }

    public static function infolist(Schema $schema): Schema
    {
        return VerificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VerificationsTable::configure($table);
    }

    /**
     * Verifications are immutable evidence — they are never created or edited
     * through a form, only produced by the verification action and viewed.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVerifications::route('/'),
            'view' => ViewVerification::route('/{record}'),
        ];
    }
}
