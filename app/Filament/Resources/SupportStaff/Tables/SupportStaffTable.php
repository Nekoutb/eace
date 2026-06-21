<?php

namespace App\Filament\Resources\SupportStaff\Tables;

use App\Domain\Auth\StaffRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupportStaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('support.staff.name'))
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('support.staff.email'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('staff_role')
                    ->label(__('support.staff.role'))
                    ->badge()
                    ->formatStateUsing(fn (StaffRole $state): string => $state->label())
                    ->color(fn (StaffRole $state): string => $state->color()),
                IconColumn::make('two_factor')
                    ->label('2FA')
                    ->boolean()
                    ->state(fn (User $record): bool => filled($record->app_authentication_secret)),
                TextColumn::make('telegram_username')
                    ->label(__('support.staff.telegram'))
                    ->badge()
                    ->state(fn (User $record): string => $record->telegram_username ? '@'.$record->telegram_username : (string) __('support.staff.telegram_none'))
                    ->color(fn (User $record): string => $record->hasTelegram() ? 'success' : ($record->telegram_username ? 'warning' : 'gray'))
                    ->icon(fn (User $record): ?string => $record->hasTelegram() ? 'heroicon-m-check-circle' : null),
                TextColumn::make('must_change_password')
                    ->label(__('support.staff.status'))
                    ->badge()
                    ->state(fn (User $record): string => $record->must_change_password ? (string) __('support.staff.pending_first_login') : (string) __('support.staff.active'))
                    ->color(fn (User $record): string => $record->must_change_password ? 'warning' : 'success'),
                TextColumn::make('last_login_at')
                    ->label(__('support.staff.last_login'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('support.staff.never'))
                    ->sortable(),
            ])
            ->recordActions([
                self::telegramAction(),
                DeleteAction::make()
                    ->visible(fn (User $record): bool => $record->staff_role !== StaffRole::Owner && $record->getKey() !== auth()->id()),
            ]);
    }

    /** Link / edit a staff member's Telegram identity for the escalation bridge. */
    private static function telegramAction(): Action
    {
        return Action::make('telegram')
            ->label(__('support.staff.link_telegram'))
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->fillForm(fn (User $record): array => [
                'telegram_username' => $record->telegram_username,
                'telegram_chat_id' => $record->telegram_chat_id,
            ])
            ->schema([
                TextInput::make('telegram_username')
                    ->label(__('support.staff.telegram'))
                    ->placeholder('@jane_doe')
                    ->helperText(__('support.staff.telegram_help'))
                    ->maxLength(64),
                TextInput::make('telegram_chat_id')
                    ->label(__('support.staff.telegram_chat_id'))
                    ->helperText(__('support.staff.telegram_chat_id_help'))
                    ->maxLength(64),
            ])
            ->action(function (User $record, array $data): void {
                $record->forceFill([
                    'telegram_username' => User::normaliseTelegramUsername($data['telegram_username'] ?? null),
                    'telegram_chat_id' => filled($data['telegram_chat_id'] ?? null) ? trim((string) $data['telegram_chat_id']) : null,
                ])->save();

                Notification::make()->title(__('support.staff.telegram_saved'))->success()->send();
            });
    }
}
