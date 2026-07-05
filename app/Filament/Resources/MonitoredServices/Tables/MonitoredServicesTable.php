<?php

namespace App\Filament\Resources\MonitoredServices\Tables;

use App\Domain\Monitoring\ServiceCategory;
use App\Filament\Resources\MonitoredServices\MonitoredServiceResource;
use App\Models\MonitoredService;
use App\Services\Monitoring\UptimeChecker;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MonitoredServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('renews_at', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('monitoring.f_name'))
                    ->weight('bold')
                    ->description(fn (MonitoredService $record): ?string => $record->provider)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('category')
                    ->label(__('monitoring.f_category'))
                    ->badge()
                    ->formatStateUsing(fn (ServiceCategory $state): string => $state->label())
                    ->color(fn (ServiceCategory $state): string => $state->color())
                    ->icon(fn (ServiceCategory $state): string => $state->icon()),
                TextColumn::make('powers')
                    ->label(__('monitoring.f_powers'))
                    ->placeholder('—')
                    ->limit(26)
                    ->toggleable(),
                TextColumn::make('amount')
                    ->label(__('monitoring.f_amount'))
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state, MonitoredService $record): string => $state === null
                        ? '—'
                        : number_format((float) $state, 0).' '.$record->currency.' / '.__('monitoring.cycle.'.$record->billing_cycle))
                    ->toggleable(),
                TextColumn::make('renewal_status')
                    ->label(__('monitoring.col_renewal'))
                    ->badge()
                    ->state(fn (MonitoredService $record): string => self::renewalText($record))
                    ->color(fn (MonitoredService $record): string => match ($record->renewalStatus()) {
                        'overdue' => 'danger',
                        'due_soon' => 'warning',
                        'ok' => 'success',
                        default => 'gray',
                    }),
                IconColumn::make('auto_pay')
                    ->label(__('monitoring.f_auto_pay'))
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning'),
                TextColumn::make('last_status')
                    ->label(__('monitoring.col_uptime'))
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : (string) __('monitoring.up_'.$state))
                    ->color(fn (?string $state): string => $state === 'up' ? 'success' : ($state === 'down' ? 'danger' : 'gray'))
                    ->tooltip(fn (MonitoredService $record): ?string => $record->last_checked_at?->diffForHumans()),
                IconColumn::make('is_active')
                    ->label(__('monitoring.f_active'))
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('monitoring.f_category'))
                    ->options(ServiceCategory::options()),
            ])
            ->emptyStateHeading(__('monitoring.empty_heading'))
            ->emptyStateDescription(__('monitoring.empty_desc'))
            ->emptyStateIcon('heroicon-o-signal')
            ->recordActions([
                ActionGroup::make([
                    self::editAction(),
                    self::pingAction(),
                    self::deleteAction(),
                ]),
            ]);
    }

    private static function renewalText(MonitoredService $record): string
    {
        $days = $record->daysToRenewal();

        if ($days === null) {
            return (string) __('monitoring.no_date');
        }
        if ($days < 0) {
            return (string) __('monitoring.overdue_days', ['n' => abs($days)]);
        }
        if ($days === 0) {
            return (string) __('monitoring.due_today');
        }

        return (string) __('monitoring.in_days', ['n' => $days]);
    }

    private static function editAction(): Action
    {
        return Action::make('edit')
            ->label(__('monitoring.edit'))
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->fillForm(fn (MonitoredService $record): array => [
                'name' => $record->name,
                'provider' => $record->provider,
                'category' => $record->category->value,
                'powers' => $record->powers,
                'amount' => $record->amount,
                'currency' => $record->currency,
                'billing_cycle' => $record->billing_cycle,
                'renews_at' => $record->renews_at?->toDateString(),
                'auto_pay' => $record->auto_pay,
                'health_url' => $record->health_url,
                'is_active' => $record->is_active,
                'notes' => $record->notes,
            ])
            ->schema(MonitoredServiceResource::formSchema())
            ->action(fn (MonitoredService $record, array $data) => $record->update($data));
    }

    private static function pingAction(): Action
    {
        return Action::make('ping')
            ->label(__('monitoring.ping'))
            ->icon('heroicon-o-signal')
            ->color('gray')
            ->action(function (MonitoredService $record): void {
                if (blank($record->health_url)) {
                    Notification::make()->title(__('monitoring.no_url'))->warning()->send();

                    return;
                }

                app(UptimeChecker::class)->check($record);
                $record->refresh();

                Notification::make()
                    ->title((string) __('monitoring.up_'.($record->last_status ?? 'down')).' (HTTP '.$record->last_http_code.')')
                    ->color($record->last_status === 'up' ? 'success' : 'danger')
                    ->send();
            });
    }

    private static function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('monitoring.delete'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn (MonitoredService $record) => $record->delete());
    }
}
