<?php

namespace App\Filament\Resources\DataBreaches\Tables;

use App\Filament\Resources\DataBreaches\DataBreachResource;
use App\Models\DataBreach;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DataBreachesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('detected_at', 'desc')
            ->columns([
                TextColumn::make('detected_at')->label(__('dp.breach.detected_at'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('description')->label(__('dp.breach.description'))->wrap()->limit(60)->searchable(),
                TextColumn::make('risk')->label(__('dp.breach.risk'))->badge()
                    ->formatStateUsing(fn (string $state): string => (string) __('dp.breach.risk_'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'danger', 'medium' => 'warning', default => 'gray',
                    }),
                TextColumn::make('status')->label(__('dp.breach.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => (string) __('dp.breach.st_'.$state))
                    ->color(fn (string $state): string => $state === 'closed' ? 'success' : 'gray'),
                TextColumn::make('authority_notified_at')->label(__('dp.breach.authority_notified_at'))
                    ->dateTime('d/m/Y H:i')->placeholder('—')->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'danger')->toggleable(),
                TextColumn::make('subjects_notified_at')->label(__('dp.breach.subjects_notified_at'))
                    ->dateTime('d/m/Y H:i')->placeholder('—')->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'danger')->toggleable(),
                TextColumn::make('reported_by')->label(__('dp.breach.reported_by'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label(__('dp.breach.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->fillForm(fn (DataBreach $record): array => $record->attributesToArray())
                        ->schema(DataBreachResource::formSchema())
                        ->action(function (DataBreach $record, array $data): void {
                            $record->update($data);
                            Notification::make()->title(__('dp.breach.marked'))->success()->send();
                        }),
                    Action::make('mark_authority')
                        ->label(__('dp.breach.mark_authority'))
                        ->icon('heroicon-o-building-library')
                        ->visible(fn (DataBreach $record): bool => $record->authority_notified_at === null)
                        ->requiresConfirmation()
                        ->action(function (DataBreach $record): void {
                            $record->forceFill(['authority_notified_at' => now()])->save();
                            Notification::make()->title(__('dp.breach.marked'))->success()->send();
                        }),
                    Action::make('mark_subjects')
                        ->label(__('dp.breach.mark_subjects'))
                        ->icon('heroicon-o-users')
                        ->visible(fn (DataBreach $record): bool => $record->subjects_notified_at === null)
                        ->requiresConfirmation()
                        ->action(function (DataBreach $record): void {
                            $record->forceFill(['subjects_notified_at' => now()])->save();
                            Notification::make()->title(__('dp.breach.marked'))->success()->send();
                        }),
                ]),
            ]);
    }
}
