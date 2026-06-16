<?php

namespace App\Filament\App\Resources\AcfCertificates\Schemas;

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfStatus;
use App\Models\AcfCertificate;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class AcfCertificateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('app.ui.section_acf'))
                ->columns(2)
                ->components([
                    TextEntry::make('status')
                        ->label(__('app.ui.validity'))
                        ->badge()
                        ->formatStateUsing(fn (AcfStatus $state): string => $state->label())
                        ->color(fn (AcfStatus $state): string => $state->color())
                        ->icon(fn (AcfStatus $state): string => $state->icon()),
                    TextEntry::make('authenticity_status')
                        ->label(__('app.ui.authenticity_harmony'))
                        ->badge()
                        ->placeholder(__('app.ui.placeholder_no_acf'))
                        ->formatStateUsing(fn (AcfAuthenticityStatus $state): string => $state->label())
                        ->color(fn (AcfAuthenticityStatus $state): string => $state->color()),
                    TextEntry::make('reference')
                        ->label(__('app.ui.reference'))
                        ->fontFamily(FontFamily::Mono)
                        ->copyable(),
                    TextEntry::make('niu')
                        ->label(__('app.ui.niu'))
                        ->fontFamily(FontFamily::Mono)
                        ->placeholder(__('app.ui.placeholder_dash'))
                        ->copyable(),
                    TextEntry::make('taxpayer_name')
                        ->label(__('app.ui.taxpayer'))
                        ->placeholder(__('app.ui.placeholder_dash')),
                    TextEntry::make('regime')
                        ->label(__('app.ui.regime'))
                        ->placeholder(__('app.ui.placeholder_dash')),
                    TextEntry::make('issued_at')
                        ->label(__('app.ui.issued_at'))
                        ->date('d/m/Y'),
                    TextEntry::make('expires_at')
                        ->label(__('app.ui.expires_at'))
                        ->date('d/m/Y'),
                    TextEntry::make('validity_months')
                        ->label(__('app.ui.validity_duration'))
                        ->suffix(' '.__('app.ui.months')),
                    TextEntry::make('centre')
                        ->label(__('app.ui.centre'))
                        ->placeholder(__('app.ui.placeholder_dash')),
                    TextEntry::make('authenticity_url')
                        ->label(__('app.ui.auth_url'))
                        ->placeholder(__('app.ui.placeholder_dash'))
                        ->url(fn (AcfCertificate $record): ?string => $record->authenticity_url)
                        ->openUrlInNewTab()
                        ->columnSpanFull(),
                ]),
            Section::make(__('app.ui.section_auth_evidence'))
                ->description(__('app.ui.section_auth_evidence_desc'))
                ->columns(2)
                ->components([
                    TextEntry::make('last_verified_at')
                        ->label(__('app.ui.ev_verified_at'))
                        ->dateTime('d/m/Y H:i')
                        ->placeholder(__('app.ui.no_snapshot_yet')),
                    TextEntry::make('authenticity_status')
                        ->label(__('app.ui.authenticity'))
                        ->badge()
                        ->placeholder(__('app.acf_auth.unverified'))
                        ->formatStateUsing(fn (AcfAuthenticityStatus $state): string => $state->label())
                        ->color(fn (AcfAuthenticityStatus $state): string => $state->color()),
                    ViewEntry::make('authenticity_snapshot')
                        ->hiddenLabel()
                        ->view('filament.app.acf-auth-snapshot')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
