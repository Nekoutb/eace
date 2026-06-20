<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Central. One row per tenant user's sign-in email → its workspace (tenant id).
 * The shared, no-subdomain login resolves which workspace an email belongs to
 * through this directory; kept in sync from the tenant users.
 *
 * @property string $email
 * @property string $tenant_id
 */
class TenantLogin extends CentralModel
{
    protected $table = 'tenant_logins';

    protected $guarded = ['id'];

    /** The tenant id this sign-in email belongs to, or null if unknown. */
    public static function tenantFor(string $email): ?string
    {
        $value = static::query()->where('email', $email)->value('tenant_id');

        return $value !== null ? (string) $value : null;
    }

    /** Whether this email is already claimed by a DIFFERENT workspace. */
    public static function isClaimedByAnotherWorkspace(string $email, string $tenantId): bool
    {
        return static::query()
            ->where('email', $email)
            ->where('tenant_id', '!=', $tenantId)
            ->exists();
    }

    /**
     * Point a sign-in email at a workspace (idempotent within the same workspace).
     *
     * One email belongs to exactly one workspace. If the email is already claimed
     * by a DIFFERENT workspace this is a no-op — a tenant must never silently
     * re-point another tenant's directory entry to itself (a cross-tenant login
     * takeover). Callers prevent this upstream (provisioning + the add-user form);
     * this is the safe-by-default backstop.
     */
    public static function link(string $email, string $tenantId): void
    {
        if (static::isClaimedByAnotherWorkspace($email, $tenantId)) {
            return;
        }

        static::query()->updateOrCreate(['email' => $email], ['tenant_id' => $tenantId]);
    }

    /**
     * Drop a sign-in email from the directory. Scoped to a workspace when given, so
     * a delete in one tenant can never remove another tenant's entry for the same
     * email.
     */
    public static function unlink(string $email, ?string $tenantId = null): void
    {
        $query = static::query()->where('email', $email);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $query->delete();
    }
}
