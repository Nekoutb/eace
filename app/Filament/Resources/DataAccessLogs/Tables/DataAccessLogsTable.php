<?php

namespace App\Filament\Resources\DataAccessLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DataAccessLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('dp.access.when'))->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('user_email')->label(__('dp.access.who'))->placeholder('—')->searchable(),
                TextColumn::make('action')->label(__('dp.access.action'))->badge()
                    ->color(fn (string $state): string => $state === 'export' ? 'warning' : 'gray'),
                TextColumn::make('resource')->label(__('dp.access.resource'))->searchable(),
                TextColumn::make('context')->label(__('dp.access.context'))->placeholder('—')->limit(50)->toggleable(),
                TextColumn::make('tenant_id')->label(__('dp.access.workspace'))->placeholder('—')->toggleable(),
                TextColumn::make('ip')->label(__('dp.access.ip'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->label(__('dp.access.action'))->options([
                    'export' => 'export',
                    'download' => 'download',
                    'view' => 'view',
                ]),
            ]);
    }
}
