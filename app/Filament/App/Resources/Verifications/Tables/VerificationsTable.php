<?php

namespace App\Filament\App\Resources\Verifications\Tables;

use App\Domain\Niu\Verdict;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VerificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('niu')
                    ->label('NIU')
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('— format invalide —')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('verdict')
                    ->label('Verdict')
                    ->badge()
                    ->formatStateUsing(fn (Verdict $state): string => $state->label())
                    ->color(fn (Verdict $state): string => $state->color())
                    ->icon(fn (Verdict $state): string => $state->icon()),
                TextColumn::make('thirdParty.name')
                    ->label('Tiers')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('exposure_xaf')
                    ->label('Exposition')
                    ->money('XAF', locale: 'fr')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Vérifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
