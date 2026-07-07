<?php

namespace App\Filament\Resources\Firms\Tables;

use App\Models\AccountAssignment;
use App\Models\DataAccessLog;
use App\Models\Firm;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FirmsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('firm.admin.name'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sub_accounts')
                    ->label(__('firm.admin.sub_accounts'))
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (Firm $record): string => $record->subAccountCount().' / '.$record->max_sub_accounts),
                TextColumn::make('members')
                    ->label(__('firm.admin.members'))
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (Firm $record): int => $record->members()->count()),
                TextColumn::make('assignments')
                    ->label(__('firm.admin.assignments'))
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (Firm $record): int => AccountAssignment::query()
                        ->whereIn('firm_member_id', $record->members()->pluck('id'))
                        ->count()),
                TextColumn::make('is_active')
                    ->label(__('app.ui.status'))
                    ->badge()
                    ->getStateUsing(fn (Firm $record): string => $record->is_active
                        ? (string) __('app.client.active')
                        : (string) __('app.client.suspended'))
                    ->color(fn (Firm $record): string => $record->is_active ? 'success' : 'danger'),
                TextColumn::make('created_at')
                    ->label(__('app.client.created'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->emptyStateHeading(__('firm.admin.empty_heading'))
            ->emptyStateDescription(__('firm.admin.empty_desc'))
            ->emptyStateIcon('heroicon-o-briefcase')
            ->recordActions([
                ActionGroup::make([
                    self::editPlanAction(),
                    self::deactivateAction(),
                    self::reactivateAction(),
                ])->label(__('app.client.actions')),
            ]);
    }

    /** The operator's commercial levers: rename + the sub-account cap + default quota. */
    private static function editPlanAction(): Action
    {
        return Action::make('editPlan')
            ->label(__('firm.admin.edit_plan'))
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('gray')
            ->fillForm(fn (Firm $record): array => [
                'name' => $record->name,
                'max_sub_accounts' => $record->max_sub_accounts,
                'default_quota_mb' => $record->default_quota_mb,
            ])
            ->schema([
                TextInput::make('name')->label(__('firm.admin.name'))->required()->maxLength(255),
                TextInput::make('max_sub_accounts')->label(__('firm.admin.cap'))->numeric()->required()->minValue(1),
                TextInput::make('default_quota_mb')->label(__('firm.admin.default_quota'))->numeric()->required()->minValue(1)->suffix('MB'),
            ])
            ->action(function (Firm $record, array $data): void {
                $record->update([
                    'name' => (string) $data['name'],
                    'max_sub_accounts' => (int) $data['max_sub_accounts'],
                    'default_quota_mb' => (int) $data['default_quota_mb'],
                ]);

                Notification::make()->title(__('firm.admin.plan_updated'))->success()->send();
            });
    }

    /** Deactivating a firm suspends EVERY one of its sub-accounts at once. */
    private static function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->label(__('firm.admin.deactivate'))
            ->icon('heroicon-o-pause-circle')
            ->color('danger')
            ->visible(fn (Firm $record): bool => $record->is_active)
            ->requiresConfirmation()
            ->modalDescription(__('firm.admin.deactivate_desc'))
            ->action(function (Firm $record): void {
                $record->update(['is_active' => false]);
                DataAccessLog::record('firm_deactivate', 'firm:'.$record->getKey(), 'by admin '.(string) auth()->user()?->getAttribute('email'));

                Notification::make()->title(__('firm.admin.deactivated_done'))->warning()->send();
            });
    }

    private static function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->label(__('app.client.reactivate'))
            ->icon('heroicon-o-play-circle')
            ->color('success')
            ->visible(fn (Firm $record): bool => ! $record->is_active)
            ->requiresConfirmation()
            ->action(function (Firm $record): void {
                $record->update(['is_active' => true]);
                DataAccessLog::record('firm_reactivate', 'firm:'.$record->getKey(), 'by admin '.(string) auth()->user()?->getAttribute('email'));

                Notification::make()->title(__('app.client.reactivated_done'))->success()->send();
            });
    }
}
