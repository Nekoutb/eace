<?php

namespace App\Filament\App\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;

/**
 * Forced first-login password change. A freshly provisioned user signs in with
 * a one-time password (must_change_password = true); the ForcePasswordChange
 * middleware funnels them here until they set their own. Not in the navigation —
 * it is reached only by that redirect.
 */
class ChangePassword extends Page
{
    protected string $view = 'filament.app.pages.change-password';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    public ?string $password = null;

    public ?string $password_confirmation = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return (string) __('app.password_change.title');
    }

    public function save(): void
    {
        $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = auth()->user();
        $user->forceFill([
            'password' => Hash::make((string) $this->password),
            'must_change_password' => false,
            'last_login_at' => now(),
        ])->save();

        $this->reset(['password', 'password_confirmation']);

        Notification::make()->title(__('app.password_change.done'))->success()->send();

        $this->redirect(Dashboard::getUrl());
    }
}
