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
 * table (workspace, role, 2FA, last sign-in, phone), with per-row actions:
 * resend credentials, set/edit the phone, and delete (last-admin guarded). It
 * also flags users who have gone quiet (no sign-in for > N working days) and can
 * filter to just them, so the team can reach out. Operators (landlord) only.
 */
class AllUsers extends Page
{
    protected string $view = 'filament.pages.all-users';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 18;

    /** Show only customers who have gone quiet (inactive > N working days). */
    public bool $onlyInactive = false;

    // Set-phone modal state.
    public bool $showPhoneModal = false;

    public ?string $editTenantId = null;

    public ?int $editUserId = null;

    public ?string $editUserLabel = null;

    public ?string $phoneInput = null;

    /**
     * Memoised per request — all() walks every tenant DB, so never do it twice.
     *
     * @var list<array<string, mixed>>|null
     */
    private ?array $directoryCache = null;

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
    private function directory(): array
    {
        return $this->directoryCache ??= app(WorkspaceUserDirectory::class)->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        $rows = $this->directory();

        return $this->onlyInactive
            ? array_values(array_filter($rows, static fn (array $r): bool => (bool) ($r['inactive'] ?? false)))
            : $rows;
    }

    /** How many users, across all workspaces, are currently inactive. */
    public function inactiveCount(): int
    {
        return count(array_filter($this->directory(), static fn (array $r): bool => (bool) ($r['inactive'] ?? false)));
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

    /** Open the set-phone modal for one user. */
    public function openPhone(string $tenantId, int $userId, ?string $current, string $label): void
    {
        $this->editTenantId = $tenantId;
        $this->editUserId = $userId;
        $this->editUserLabel = $label;
        $this->phoneInput = $current;
        $this->showPhoneModal = true;
    }

    public function savePhone(): void
    {
        if ($this->editTenantId === null || $this->editUserId === null) {
            return;
        }

        $this->validate(['phoneInput' => ['nullable', 'string', 'max:30']]);

        try {
            app(WorkspaceUserDirectory::class)->updatePhone($this->editTenantId, (int) $this->editUserId, $this->phoneInput);
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title((string) __('app.allusers.action_failed'))->danger()->send();

            return;
        }

        $this->closePhone();
        Notification::make()->title((string) __('app.allusers.phone_saved'))->success()->send();
    }

    public function closePhone(): void
    {
        $this->showPhoneModal = false;
        $this->reset(['editTenantId', 'editUserId', 'editUserLabel', 'phoneInput']);
    }
}
