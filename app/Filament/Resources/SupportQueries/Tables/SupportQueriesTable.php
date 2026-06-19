<?php

namespace App\Filament\Resources\SupportQueries\Tables;

use App\Models\SupportQuery;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportQueriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('app.support.received_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('tenant_name')
                    ->label(__('app.support.workspace'))
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user_name')
                    ->label(__('app.support.from'))
                    ->description(fn (SupportQuery $record): ?string => $record->user_email)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('message')
                    ->label(__('app.support.message'))
                    ->wrap()
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('app.support.status'))
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => (string) __('app.support.status_'.$state))
                    ->color(fn (string $state): string => $state === 'answered' ? 'success' : 'warning'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('app.support.status'))
                    ->options([
                        'new' => (string) __('app.support.status_new'),
                        'answered' => (string) __('app.support.status_answered'),
                    ]),
            ])
            ->recordActions([
                self::respondAction(),
            ]);
    }

    /** Read the full question and reply — the reply is shown to the submitter. */
    private static function respondAction(): Action
    {
        return Action::make('respond')
            ->label(__('app.support.respond'))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('success')
            ->modalHeading(__('app.support.respond'))
            ->modalSubmitActionLabel(__('app.support.send_reply'))
            ->fillForm(fn (SupportQuery $record): array => ['response' => $record->response])
            ->schema([
                Placeholder::make('question')
                    ->label(__('app.support.message'))
                    ->content(fn (SupportQuery $record): string => $record->message),
                Textarea::make('response')
                    ->label(__('app.support.reply_label'))
                    ->required()
                    ->rows(5)
                    ->maxLength(4000),
            ])
            ->action(function (SupportQuery $record, array $data): void {
                $record->update([
                    'response' => trim((string) $data['response']),
                    'status' => 'answered',
                    'answered_at' => now(),
                ]);

                Notification::make()->title(__('app.support.notif_answered'))->success()->send();
            });
    }
}
