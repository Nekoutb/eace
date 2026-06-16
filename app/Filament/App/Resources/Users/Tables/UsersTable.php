<?php

namespace App\Filament\App\Resources\Users\Tables;

use App\Domain\Auth\UserRole;
use App\Filament\App\Resources\Users\UserResource;
use App\Models\User;
use App\Support\TempPassword;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.user.name'))
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('app.user.email'))
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('app.user.role'))
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->color(fn (UserRole $state): string => $state->color()),
                IconColumn::make('must_change_password')
                    ->label(__('app.user.must_change'))
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),
                TextColumn::make('last_login_at')
                    ->label(__('app.user.last_login'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('app.user.never')),
                TextColumn::make('created_at')
                    ->label(__('app.user.created'))
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->recordActions([
                self::editAction(),
                self::resetPasswordAction(),
                self::deleteAction(),
            ]);
    }

    private static function editAction(): Action
    {
        return Action::make('edit')
            ->label(__('app.client.rename'))
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->fillForm(fn (User $record): array => ['name' => $record->name, 'role' => $record->role?->value])
            ->schema([
                TextInput::make('name')->label(__('app.user.name'))->required()->maxLength(255),
                Select::make('role')->label(__('app.user.role'))->required()->options(UserRole::options())->native(false),
            ])
            ->action(function (User $record, array $data): void {
                DB::transaction(function () use ($record, $data): void {
                    // Never let the organisation lose its last administrator.
                    if ($data['role'] !== UserRole::Admin->value && self::wouldOrphanAdmins($record)) {
                        self::deny(__('app.user.cannot_demote_last_admin'));

                        return;
                    }

                    $record->update(['name' => $data['name'], 'role' => $data['role']]);
                });
            });
    }

    private static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label(__('app.user.reset_password'))
            ->icon('heroicon-o-key')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('app.user.reset_desc'))
            ->action(function (User $record): void {
                $temporaryPassword = TempPassword::generate();
                $record->update([
                    'password' => Hash::make($temporaryPassword),
                    'must_change_password' => true,
                ]);

                UserResource::credentialsNotification(
                    (string) __('app.user.reset_title'),
                    (string) $record->email,
                    $temporaryPassword,
                );
            });
    }

    private static function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('app.user.delete'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('app.user.delete_warning'))
            ->action(function (User $record): void {
                if ($record->getKey() === auth()->id()) {
                    self::deny(__('app.user.cannot_delete_self'));

                    return;
                }

                DB::transaction(function () use ($record): void {
                    if (self::wouldOrphanAdmins($record)) {
                        self::deny(__('app.user.cannot_delete_last_admin'));

                        return;
                    }

                    $record->delete();
                });
            });
    }

    /**
     * Whether removing/demoting this user would leave zero admins. The locked
     * count of OTHER admins is atomic on row-locking databases (PostgreSQL, the
     * production target) when run inside the action's transaction; on SQLite the
     * lock is a no-op but the count-others logic still blocks the serial case.
     */
    private static function wouldOrphanAdmins(User $record): bool
    {
        return $record->isAdmin()
            && User::query()
                ->where('role', UserRole::Admin->value)
                ->whereKeyNot($record->getKey())
                ->lockForUpdate()
                ->doesntExist();
    }

    private static function deny(string $message): void
    {
        Notification::make()->title($message)->danger()->send();
    }
}
