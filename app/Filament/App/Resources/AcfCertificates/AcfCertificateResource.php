<?php

namespace App\Filament\App\Resources\AcfCertificates;

use App\Filament\App\Resources\AcfCertificates\Pages\ListAcfCertificates;
use App\Filament\App\Resources\AcfCertificates\Pages\ViewAcfCertificate;
use App\Filament\App\Resources\AcfCertificates\Schemas\AcfCertificateInfolist;
use App\Filament\App\Resources\AcfCertificates\Tables\AcfCertificatesTable;
use App\Models\AcfCertificate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AcfCertificateResource extends Resource
{
    protected static ?string $model = AcfCertificate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.certificates');
    }

    public static function getModelLabel(): string
    {
        return (string) __('app.nav.certificates');
    }

    public static function infolist(Schema $schema): Schema
    {
        return AcfCertificateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AcfCertificatesTable::configure($table);
    }

    /** Certificates are only created by importing an ACF, never via a blank form. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcfCertificates::route('/'),
            'view' => ViewAcfCertificate::route('/{record}'),
        ];
    }
}
