<?php

namespace App\Filament\Pages;

use App\Services\Tenant\WorkspaceUserDirectory;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Central admin. "All users" — every user across every client workspace in one
 * table (workspace, role, 2FA, last sign-in), with per-row actions: resend
 * credentials (fresh one-time password, emailed + shown once) and delete
 * (last-admin guarded). Operators (landlord) only.
 */
class AllUsers extends Page
{
    protected string $view = 'filament.pages.all-users';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 18;

    public static function canAccess(): bool
    {
        return auth()->user()?->isCentralUser() === true;
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('app.allusers.nav');
    }

    public function getTitle(): string
    {
        return (string) __('app.allusers.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('app.allusers.subheading');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return app(WorkspaceUserDirectory::class)->all();
    }

    public function resend(string $tenantId, int $userId): void
    {
        try {
            $temporaryPassword = app(WorkspaceUserDirectory::class)->resendCredentials($tenantId, $userId);
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title((string) __('app.allusers.action_failed'))->danger()->send();

            return;
        }

        Notification::make()
            ->title((string) __('app.allusers.resent_title'))
            ->body(new HtmlString(
                '<div style="line-height:1.7">'.e(__('app.allusers.resent_body'))
                .'<br>'.e(__('app.user.temp_password')).': <code>'.e($temporaryPassword).'</code></div>'
            ))
            ->success()
            ->persistent()
            ->send();
    }

    public function deleteUser(string $tenantId, int $userId): void
    {
        try {
            app(WorkspaceUserDirectory::class)->deleteUser($tenantId, $userId);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title((string) __('app.allusers.deleted_title'))->success()->send();
    }
}
