<?php

namespace App\Filament\App\Resources\AcfCertificates\Tables;

use App\Domain\Acf\AcfStatus;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AcfCertificatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expires_at', 'asc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence ACF')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('niu')
                    ->label('NIU')
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('taxpayer_name')
                    ->label('Contribuable')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Validité')
                    ->badge()
                    ->formatStateUsing(fn (AcfStatus $state): string => $state->label())
                    ->color(fn (AcfStatus $state): string => $state->color())
                    ->icon(fn (AcfStatus $state): string => $state->icon()),
                TextColumn::make('issued_at')
                    ->label('Signée le')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
