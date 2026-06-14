<?php

namespace App\Filament\App\Resources\AcfCertificates\Schemas;

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class AcfCertificateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Attestation de Conformité Fiscale')
                ->columns(2)
                ->components([
                    TextEntry::make('status')
                        ->label('Validité')
                        ->badge()
                        ->formatStateUsing(fn (AcfStatus $state): string => $state->label())
                        ->color(fn (AcfStatus $state): string => $state->color())
                        ->icon(fn (AcfStatus $state): string => $state->icon()),
                    TextEntry::make('authenticity_status')
                        ->label('Authenticité (Harmony2)')
                        ->badge()
                        ->placeholder('— non vérifiée —')
                        ->formatStateUsing(fn (AcfAuthenticityStatus $state): string => $state->label())
                        ->color(fn (AcfAuthenticityStatus $state): string => $state->color()),
                    TextEntry::make('reference')
                        ->label('Référence ACF')
                        ->fontFamily(FontFamily::Mono)
                        ->copyable(),
                    TextEntry::make('niu')
                        ->label('NIU')
                        ->fontFamily(FontFamily::Mono)
                        ->placeholder('—')
                        ->copyable(),
                    TextEntry::make('taxpayer_name')
                        ->label('Contribuable')
                        ->placeholder('—'),
                    TextEntry::make('regime')
                        ->label('Régime')
                        ->placeholder('—'),
                    TextEntry::make('issued_at')
                        ->label('Signée le')
                        ->date('d/m/Y'),
                    TextEntry::make('expires_at')
                        ->label('Expire le')
                        ->date('d/m/Y'),
                    TextEntry::make('validity_months')
                        ->label('Durée de validité')
                        ->suffix(' mois'),
                    TextEntry::make('centre')
                        ->label('Centre des impôts')
                        ->placeholder('—'),
                    TextEntry::make('authenticity_url')
                        ->label('Lien d’authentification')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
