<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Domain\Auth\UserRole;
use App\Mail\WorkspaceCredentialsMail;
use App\Models\DataAccessLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TempPassword;
use App\Support\WorkingDays;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * The operator's cross-workspace view of every user in the tool: list them all
 * (workspace, role, 2FA, last sign-in), resend credentials, delete. Each action
 * runs inside the user's own workspace database; the last-admin guard is enforced
 * here exactly as in the client-side Users screen — a workspace can never lose
 * its last administrator, not even from the admin console. Actions are audited
 * centrally.
 */
final class WorkspaceUserDirectory
{
    /**
     * Every user across every client workspace, workspace by workspace.
     * Best-effort: a broken workspace database is skipped, never an error.
     *
     * @return list<array{tenant_id: string, tenant_name: ?string, user_id: int, name: string, email: string, phone: ?string, role: ?string, two_fa: bool, must_change: bool, last_login: ?string, idle_working_days: ?int, inactive: bool, is_last_admin: bool}>
     */
    public function all(): array
    {
        $rows = [];
        // A user is "inactive" once more than this many working days (Mon–Fri)
        // have passed since their last sign-in — or if they never signed in.
        $threshold = (int) config('niu.inactivity.working_days', 5);

        foreach (Tenant::query()->cursor() as $tenant) {
            try {
                $users = $tenant->run(function () use ($threshold) {
                    $adminCount = User::query()->where('role', UserRole::Admin->value)->count();

                    return User::query()->orderBy('id')->get()->map(fn (User $u): array => [
                        'user_id' => (int) $u->getKey(),
                        'name' => (string) $u->name,
                        'email' => (string) $u->email,
                        'phone' => $u->phone,
                        'role' => $u->role?->value,
                        'two_fa' => filled($u->app_authentication_secret),
                        'must_change' => (bool) $u->must_change_password,
                        'last_login' => $u->last_login_at?->toDateTimeString(),
                        'idle_working_days' => $u->last_login_at !== null ? WorkingDays::between($u->last_login_at, now()) : null,
                        'inactive' => WorkingDays::isInactive($u->last_login_at, $threshold),
                        'is_last_admin' => $u->isAdmin() && $adminCount <= 1,
                    ])->all();
                });
            } catch (Throwable $e) {
                report($e);
                $this->restoreCentralContext();

                continue;
            }

            foreach ((array) $users as $user) {
                $rows[] = ['tenant_id' => (string) $tenant->getTenantKey(), 'tenant_name' => $tenant->name] + $user;
            }
        }

        return $rows;
    }

    /**
     * Issue a fresh one-time password to a workspace user and EMAIL it to them
     * (queued welcome mail with the sign-in button); forces a change + fresh 2FA
     * continues to apply on first sign-in. Returns the one-time password so the
     * operator can also read it out on a call.
     */
    public function resendCredentials(string $tenantId, int $userId): string
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $temporaryPassword = TempPassword::generate();

        try {
            $email = $tenant->run(function () use ($userId, $temporaryPassword, $tenant): string {
                $user = User::query()->findOrFail($userId);
                $user->forceFill([
                    'password' => Hash::make($temporaryPassword),
                    'must_change_password' => true,
                ])->save();

                Mail::to((string) $user->email)->locale(app()->getLocale())->queue(new WorkspaceCredentialsMail(
                    name: (string) $user->name,
                    orgName: (string) ($tenant->name ?? config('app.name')),
                    email: (string) $user->email,
                    temporaryPassword: $temporaryPassword,
                    loginUrl: rtrim((string) config('app.url'), '/').'/app/login',
                ));

                return (string) $user->email;
            });
        } catch (Throwable $e) {
            $this->restoreCentralContext();
            throw $e;
        }

        DataAccessLog::record('admin_resend_credentials', 'tenant:'.$tenantId, 'user '.$email);

        return $temporaryPassword;
    }

    /**
     * Delete a workspace user from the admin console. The workspace's LAST
     * administrator can never be deleted (same rule as the client-side screen).
     */
    public function deleteUser(string $tenantId, int $userId): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        try {
            $email = $tenant->run(function () use ($userId): string {
                $user = User::query()->findOrFail($userId);

                $lastAdmin = $user->isAdmin()
                    && User::query()->where('role', UserRole::Admin->value)->whereKeyNot($user->getKey())->doesntExist();

                if ($lastAdmin) {
                    throw new RuntimeException((string) __('app.user.cannot_delete_last_admin'));
                }

                $email = (string) $user->email;
                $user->delete(); // instance delete → the observer unlinks the directory row

                return $email;
            });
        } catch (Throwable $e) {
            $this->restoreCentralContext();
            throw $e;
        }

        DataAccessLog::record('admin_delete_user', 'tenant:'.$tenantId, 'user '.$email);
    }

    /**
     * Set (or clear) a workspace user's phone number from the admin console — a
     * contact detail only, so the team can reach the person; it never
     * authenticates anything. An empty value clears it.
     */
    public function updatePhone(string $tenantId, int $userId, ?string $phone): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $clean = trim((string) $phone);
        $value = $clean !== '' ? $clean : null;

        try {
            $email = $tenant->run(function () use ($userId, $value): string {
                $user = User::query()->findOrFail($userId);
                $user->update(['phone' => $value]);

                return (string) $user->email;
            });
        } catch (Throwable $e) {
            $this->restoreCentralContext();
            throw $e;
        }

        DataAccessLog::record('admin_update_phone', 'tenant:'.$tenantId, 'user '.$email);
    }

    /** Tenant::run() does not restore context when the closure throws. */
    private function restoreCentralContext(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}
