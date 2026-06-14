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
                    ->label(__('app.ui.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('niu')
                    ->label(__('app.ui.niu'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->searchable(),
                TextColumn::make('taxpayer_name')
                    ->label(__('app.ui.taxpayer'))
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('app.ui.validity'))
                    ->badge()
                    ->formatStateUsing(fn (AcfStatus $state): string => $state->label())
                    ->color(fn (AcfStatus $state): string => $state->color())
                    ->icon(fn (AcfStatus $state): string => $state->icon()),
                TextColumn::make('issued_at')
                    ->label(__('app.ui.issued_at'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('app.ui.expires_at'))
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
