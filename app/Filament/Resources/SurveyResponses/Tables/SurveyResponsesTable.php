<?php

namespace App\Filament\Resources\SurveyResponses\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SurveyResponsesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('submitted_at')
                    ->label(__('support.survey.submitted_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('tenant_name')
                    ->label(__('support.console.workspace'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('service_quality')
                    ->label(__('support.survey.service_quality'))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : $state.'/5')
                    ->badge()
                    ->color(fn (?int $state): string => self::likertColor($state)),
                TextColumn::make('ease_of_use')
                    ->label(__('support.survey.ease_of_use'))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : $state.'/5')
                    ->badge()
                    ->color(fn (?int $state): string => self::likertColor($state)),
                TextColumn::make('responsiveness')
                    ->label(__('support.survey.responsiveness'))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : $state.'/5')
                    ->badge()
                    ->color(fn (?int $state): string => self::likertColor($state))
                    ->toggleable(),
                TextColumn::make('recommend')
                    ->label(__('support.survey.recommend'))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : $state.'/10')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 9 => 'success',
                        $state >= 7 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('comment')
                    ->label(__('support.survey.comment'))
                    ->wrap()
                    ->limit(80)
                    ->placeholder('—')
                    ->searchable(),
            ]);
    }

    private static function likertColor(?int $state): string
    {
        return match (true) {
            $state === null => 'gray',
            $state >= 4 => 'success',
            $state >= 3 => 'warning',
            default => 'danger',
        };
    }
}
