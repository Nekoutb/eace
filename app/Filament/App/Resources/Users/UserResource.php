<?php

namespace App\Filament\App\Resources\Users;

use App\Filament\App\Resources\Users\Pages\ListUsers;
use App\Filament\App\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Tenant panel. Lets a client's administrators manage their own staff. Only
 * administrators may reach it — operators never see it (canAccess +
 * shouldRegisterNavigation are both gated on isAdmin).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.users');
    }

    public static function getModelLabel(): string
    {
        return (string) __('app.nav.users');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false; // users are added via the guided "Add a user" action
    }

    /**
     * A persistent toast carrying one-time credentials. The temporary password
     * is shown here once and never persisted in plain text.
     */
    public static function credentialsNotification(string $title, string $email, string $temporaryPassword): void
    {
        Notification::make()
            ->title($title)
            ->body(new HtmlString(
                '<div style="line-height:1.7">'
                .e(__('app.client.cred_email')).': <code>'.e($email).'</code><br>'
                .e(__('app.user.temp_password')).': <code>'.e($temporaryPassword).'</code><br>'
                .'<em>'.e(__('app.client.cred_once')).'</em></div>'
            ))
            ->success()
            ->persistent()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
