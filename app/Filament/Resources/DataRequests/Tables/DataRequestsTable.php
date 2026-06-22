<?php

namespace App\Filament\Resources\DataRequests\Tables;

use App\Models\DataRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DataRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('dp.request.created'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('tenant_name')->label(__('dp.request.workspace'))->placeholder('—')->searchable(),
                TextColumn::make('requester_email')->label(__('dp.request.requester'))
                    ->description(fn (DataRequest $r): ?string => $r->requester_name)->searchable(),
                TextColumn::make('type')->label(__('dp.request.type'))->badge()
                    ->formatStateUsing(fn (string $state): string => (string) __('dp.request.t_'.$state)),
                TextColumn::make('status')->label(__('dp.request.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => (string) __('dp.request.st_'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success', 'refused' => 'danger', 'in_progress' => 'warning', default => 'gray',
                    }),
                TextColumn::make('due_at')->label(__('dp.request.due'))->date('d/m/Y')->placeholder('—')
                    ->color(fn (DataRequest $r): string => $r->due_at && $r->handled_at === null && now()->greaterThan($r->due_at) ? 'danger' : 'gray')
                    ->badge()->toggleable(),
                TextColumn::make('handled_at')->label(__('dp.request.handled'))->dateTime('d/m/Y H:i')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('dp.request.status'))->options([
                    'new' => (string) __('dp.request.st_new'),
                    'in_progress' => (string) __('dp.request.st_in_progress'),
                    'completed' => (string) __('dp.request.st_completed'),
                    'refused' => (string) __('dp.request.st_refused'),
                ]),
            ])
            ->recordActions([
                Action::make('handle')
                    ->label(__('dp.request.handle'))
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (DataRequest $record): array => ['status' => $record->status, 'note' => $record->details])
                    ->schema([
                        Select::make('status')->label(__('dp.request.status'))->options([
                            'new' => (string) __('dp.request.st_new'),
                            'in_progress' => (string) __('dp.request.st_in_progress'),
                            'completed' => (string) __('dp.request.st_completed'),
                            'refused' => (string) __('dp.request.st_refused'),
                        ])->required(),
                        Textarea::make('note')->label(__('dp.request.details'))->rows(3)->maxLength(4000),
                    ])
                    ->action(function (DataRequest $record, array $data): void {
                        $done = in_array($data['status'], ['completed', 'refused'], true);
                        $record->forceFill([
                            'status' => $data['status'],
                            'details' => $data['note'] ?: $record->details,
                            'handled_by' => $done ? auth()->user()?->name : $record->handled_by,
                            'handled_at' => $done ? now() : null,
                        ])->save();

                        Notification::make()->title(__('dp.request.handled_done'))->success()->send();
                    }),
            ]);
    }
}
