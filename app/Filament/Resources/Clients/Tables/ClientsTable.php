<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Models\Tenant;
use App\Services\Evidence\EvidenceStorageService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.client.org_name'))
                    ->weight('bold')
                    ->getStateUsing(fn (Tenant $record): string => (string) ($record->name ?? $record->id))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('data->name', $direction)),
                TextColumn::make('id')
                    ->label(__('app.client.identifier'))
                    ->fontFamily(FontFamily::Mono)
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('allocated_space_mb')
                    ->label(__('app.client.quota'))
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (Tenant $record): string => number_format($record->allocatedSpaceMb()).' MB')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('data->allocated_space_mb', $direction)),
                TextColumn::make('created_at')
                    ->label(__('app.client.created'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->emptyStateHeading(__('app.client.empty_heading'))
            ->emptyStateDescription(__('app.client.empty_desc'))
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->recordActions([
                ActionGroup::make([
                    self::renameAction(),
                    self::setQuotaAction(),
                    DeleteAction::make()
                        ->modalHeading(__('app.client.delete'))
                        ->modalDescription(__('app.client.delete_warning')),
                ])->label(__('app.client.actions')),
            ]);
    }

    private static function renameAction(): Action
    {
        return Action::make('rename')
            ->label(__('app.client.rename'))
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->fillForm(fn (Tenant $record): array => ['name' => $record->name])
            ->schema([
                TextInput::make('name')
                    ->label(__('app.client.org_name'))
                    ->required()
                    ->maxLength(255),
            ])
            ->action(fn (Tenant $record, array $data) => $record->update(['name' => $data['name']]));
    }

    /**
     * Set a workspace's evidence storage quota to any value. The quota lives in the
     * stancl `data` column (written through by update), so it takes effect at once —
     * the dashboard gauge and the Clients column both read allocatedSpaceMb().
     */
    private static function setQuotaAction(): Action
    {
        return Action::make('setQuota')
            ->label(__('app.client.set_quota'))
            ->icon('heroicon-o-circle-stack')
            ->color('gray')
            ->modalHeading(__('app.client.set_quota'))
            ->modalDescription(function (Tenant $record): string {
                try {
                    $used = (float) $record->run(fn (): float => app(EvidenceStorageService::class)->usedMb());

                    return (string) __('app.client.quota_usage', [
                        'used' => number_format($used, 1),
                        'quota' => number_format($record->allocatedSpaceMb()),
                    ]);
                } catch (\Throwable) {
                    return (string) __('app.client.quota_set_desc');
                }
            })
            ->fillForm(fn (Tenant $record): array => ['allocated_space_mb' => $record->allocatedSpaceMb()])
            ->schema([
                TextInput::make('allocated_space_mb')
                    ->label(__('app.client.allocated_space'))
                    ->helperText(__('app.client.allocated_space_help'))
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->suffix('MB'),
            ])
            ->action(function (Tenant $record, array $data): void {
                $record->update(['allocated_space_mb' => (int) $data['allocated_space_mb']]);

                Notification::make()
                    ->title(__('app.client.quota_updated'))
                    ->success()
                    ->send();
            });
    }
}
