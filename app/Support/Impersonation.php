<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Auth\UserRole;
use App\Exceptions\ReadOnlyImpersonationException;
use App\Models\DataAccessLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only tenant impersonation ("badge in"): a central admin views a client's
 * /app workspace exactly as the client would, but cannot write anything.
 *
 * The session carries a marker while impersonating. Read-only is enforced in depth:
 *  - a write-blocking guard on the tenant DB connection (the hard backstop, below),
 *  - per-action gates on the app's mutating methods (denyIfReadOnly), and
 *  - the UI hiding mutating controls.
 *
 * Start/stop are audited to the central DataAccessLog. The acting admin's TOTP is
 * verified by the caller (the badge-in action) BEFORE start() — this class trusts
 * that gate and focuses on entering/leaving the workspace safely.
 */
final class Impersonation
{
    private const SESSION_KEY = 'impersonation';

    public static function isActive(): bool
    {
        return is_array(session(self::SESSION_KEY));
    }

    /** Impersonation is always read-only. */
    public static function readOnly(): bool
    {
        return self::isActive();
    }

    /**
     * @return array{admin_id: int, tenant: string, tenant_name: ?string, started_at: int}|null
     */
    public static function context(): ?array
    {
        $ctx = session(self::SESSION_KEY);

        return is_array($ctx) ? $ctx : null;
    }

    public static function tenantName(): ?string
    {
        $name = self::context()['tenant_name'] ?? null;

        return is_string($name) ? $name : null;
    }

    /**
     * Re-point an ACTIVE impersonation at a sibling workspace (the firm workspace
     * switcher, used read-only by a badged-in operator). Keeps the same admin and
     * start time — the lifetime spans the whole badge-in — so read-only stays
     * enforced throughout. No-op if not impersonating.
     */
    public static function retarget(Tenant $tenant): void
    {
        $ctx = self::context();
        if ($ctx === null) {
            return;
        }

        $ctx['tenant'] = (string) $tenant->getTenantKey();
        $ctx['tenant_name'] = $tenant->name;
        session()->put(self::SESSION_KEY, $ctx);
    }

    public static function startedAt(): ?int
    {
        $at = self::context()['started_at'] ?? null;

        return is_int($at) ? $at : null;
    }

    public static function lifetimeMinutes(): int
    {
        return (int) config('niu.impersonation.lifetime_minutes', 30);
    }

    /** An impersonation session that has run past its lifetime is auto-exited. */
    public static function expired(): bool
    {
        $startedAt = self::startedAt();

        return $startedAt !== null && (now()->timestamp - $startedAt) > self::lifetimeMinutes() * 60;
    }

    public static function canStart(?User $user): bool
    {
        return $user?->canImpersonate() === true;
    }

    /**
     * Enter a workspace as a read-only viewer: audit, initialise the tenant, sign in
     * as its administrator (the broadest client view), and pin the session. Runs
     * while the central admin is still the authenticated user. Returns false if the
     * acting user may not impersonate or the workspace has no user to view as.
     */
    public static function start(Tenant $tenant): bool
    {
        $admin = Auth::user();
        if (! $admin instanceof User || ! $admin->canImpersonate()) {
            return false;
        }
        $adminId = (int) $admin->getKey();
        $adminEmail = (string) $admin->email;

        // Audit while still acting as the admin (the actor we want recorded).
        DataAccessLog::record('impersonate_start', 'tenant:'.$tenant->getTenantKey(), 'admin '.$adminEmail);

        tenancy()->initialize($tenant);

        $viewer = User::query()->where('role', UserRole::Admin->value)->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $viewer instanceof User) {
            tenancy()->end();
            DataAccessLog::record('impersonate_failed', 'tenant:'.$tenant->getTenantKey(), 'no user to view as');

            return false;
        }

        Auth::guard('web')->login($viewer);
        self::refreshSessionPasswordHash($viewer);

        session()->put('tenant_id', $tenant->getTenantKey());
        session()->put(self::SESSION_KEY, [
            'admin_id' => $adminId,
            'tenant' => (string) $tenant->getTenantKey(),
            'tenant_name' => $tenant->name,
            'started_at' => now()->timestamp,
        ]);

        return true;
    }

    /** Leave impersonation: restore the central admin, end tenancy, clear, audit. */
    public static function stop(): void
    {
        $ctx = self::context();
        if ($ctx === null) {
            return;
        }

        session()->forget(self::SESSION_KEY);
        session()->forget('tenant_id');

        if (tenant() !== null) {
            tenancy()->end();
        }

        $admin = User::query()->find($ctx['admin_id']);
        if ($admin instanceof User) {
            Auth::guard('web')->login($admin);
            self::refreshSessionPasswordHash($admin);
            DataAccessLog::record('impersonate_end', 'tenant:'.$ctx['tenant'], 'admin '.$admin->email);
        } else {
            Auth::guard('web')->logout();
        }
    }

    /**
     * Point the session's auth password-hash at the user we just logged in as.
     * AuthenticateSession compares this hash against the current user on every
     * request and logs out on a mismatch — after swapping users we must update it,
     * or the very next request bounces back to the sign-in page.
     */
    private static function refreshSessionPasswordHash(User $user): void
    {
        session()->put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());
    }

    /**
     * Register a write-blocking guard on the given (tenant) connection while
     * impersonating. Called from the TenancyInitialized listener, so it fires for
     * both regular page loads and Livewire requests. No-op when not impersonating,
     * so normal client use is unaffected.
     *
     * The one exemption is the central `data_access_logs` table: it is the
     * landlord's compliance audit trail (never tenant data), and the impersonation
     * itself must be recordable — including a read-only workspace switch. Because
     * this deployment shares one connection for central + tenant, that audit write
     * would otherwise be blocked too.
     */
    public static function guard(Connection $connection): void
    {
        if (! self::readOnly()) {
            return;
        }

        // The callback re-checks readOnly() at execution time, so once impersonation
        // ends (same process, e.g. tests or an exit mid-request) it cleanly no-ops
        // rather than blocking a legitimate write.
        $connection->beforeExecuting(function (string $query): void {
            if (self::readOnly()
                && str_contains($query, 'data_access_logs') === false
                && preg_match('/^\s*(insert|update|delete|replace|create|alter|drop|truncate)\b/i', $query) === 1) {
                throw new ReadOnlyImpersonationException;
            }
        });
    }

    /** Per-action UI gate: refuse a mutating action cleanly while impersonating. */
    public static function denyIfReadOnly(): void
    {
        if (self::readOnly()) {
            abort(403, (string) __('app.impersonation.read_only'));
        }
    }
}
