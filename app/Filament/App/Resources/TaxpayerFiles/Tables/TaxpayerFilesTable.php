<?php

namespace App\Filament\App\Resources\TaxpayerFiles\Tables;

use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaxpayerFilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('period_label')
                    ->label(__('app.audit.period'))
                    ->weight(FontWeight::Medium)
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('app.ui.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => (string) __('app.audit.status_'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'unchanged' => 'gray',
                        'pending' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('row_count')
                    ->label(__('app.audit.taxpayers'))
                    ->numeric(locale: 'fr')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('extracted_at')
                    ->label(__('app.audit.downloaded_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->sortable(),
                TextColumn::make('file_hash')
                    ->label(__('app.audit.hash'))
                    ->fontFamily(FontFamily::Mono)
                    ->limit(16)
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('source_url')
                    ->label(__('app.audit.source_file'))
                    ->limit(40)
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
