<?php

namespace App\Filament\App\Resources\Verifications\Schemas;

use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Domain\Niu\Verdict;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;

class VerificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Décision')
                ->columns(3)
                ->components([
                    TextEntry::make('approval')
                        ->label('Approbation de la facture')
                        ->badge()
                        ->placeholder('—')
                        ->formatStateUsing(fn (ApprovalDecision $state): string => $state->label())
                        ->color(fn (ApprovalDecision $state): string => $state->color())
                        ->icon(fn (ApprovalDecision $state): string => $state->icon()),
                    TextEntry::make('stage')
                        ->label('Étape')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn (VerificationStage $state): string => $state->label()),
                    TextEntry::make('created_at')
                        ->label('Vérifié le')
                        ->dateTime('d/m/Y H:i'),
                ]),
            Section::make('Les quatre contrôles')
                ->visible(fn ($record): bool => filled($record->checks))
                ->components([
                    RepeatableEntry::make('checks')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            IconEntry::make('passed')
                                ->label('')
                                ->boolean(),
                            TextEntry::make('label')
                                ->label('Contrôle')
                                ->weight(FontWeight::Medium),
                            TextEntry::make('article')
                                ->label('Fondement')
                                ->badge()
                                ->color('gray'),
                            TextEntry::make('detail')
                                ->hiddenLabel()
                                ->columnSpanFull(),
                        ]),
                ]),
            Section::make('Verdict & exposition')
                ->columns(2)
                ->components([
                    TextEntry::make('verdict')
                        ->label('Verdict')
                        ->badge()
                        ->formatStateUsing(fn (Verdict $state): string => $state->label())
                        ->color(fn (Verdict $state): string => $state->color())
                        ->icon(fn (Verdict $state): string => $state->icon()),
                    TextEntry::make('exposure_xaf')
                        ->label('Exposition estimée')
                        ->money('XAF', locale: 'fr'),
                    TextEntry::make('niu')
                        ->label('NIU')
                        ->fontFamily(FontFamily::Mono)
                        ->placeholder('— format invalide —')
                        ->copyable(),
                    TextEntry::make('raw_input')
                        ->label('Saisie d’origine'),
                    TextEntry::make('thirdParty.name')
                        ->label('Tiers')
                        ->placeholder('—'),
                    TextEntry::make('snapshot_id')
                        ->label('Preuve (snapshot central)')
                        ->placeholder('— aucune (NIU non trouvé) —')
                        ->copyable()
                        ->columnSpanFull(),
                ]),
            Section::make('Règles déclenchées')
                ->visible(fn ($record): bool => filled($record->triggered_rules))
                ->components([
                    RepeatableEntry::make('triggered_rules')
                        ->hiddenLabel()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('title')
                                ->label('Règle')
                                ->weight(FontWeight::Medium),
                            TextEntry::make('article')
                                ->label('Fondement')
                                ->badge()
                                ->color('gray'),
                            TextEntry::make('exposure_xaf')
                                ->label('Exposition')
                                ->money('XAF', locale: 'fr'),
                            TextEntry::make('message')
                                ->hiddenLabel()
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }
}
