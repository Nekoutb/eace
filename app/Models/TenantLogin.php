<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Central. Maps a tenant user's sign-in email → its workspace(s). The shared,
 * no-subdomain login resolves which workspace an email belongs to through this
 * directory; kept in sync from the tenant users. A standalone client's email has
 * exactly one row (link, exclusive); an accounting-firm member's email may have
 * several — one per assigned sub-account (linkShared) — with (email, tenant_id)
 * unique so it maps to each workspace at most once.
 *
 * @property string $email
 * @property string $tenant_id
 */
class TenantLogin extends CentralModel
{
    protected $table = 'tenant_logins';

    protected $guarded = ['id'];

    /**
     * The workspace this sign-in email resolves to at login, or null if unknown.
     * DETERMINISTIC (ordered): a standalone client has one row; a firm member has
     * several, and always resolves to the SAME "primary" workspace — so the
     * password + 2FA checked at login are always that one workspace's, staying
     * consistent without cross-tenant credential syncing. The in-app switcher moves
     * them to their other workspaces afterwards (session-based, no re-auth).
     */
    public static function tenantFor(string $email): ?string
    {
        $value = static::query()->where('email', $email)->orderBy('tenant_id')->value('tenant_id');

        return $value !== null ? (string) $value : null;
    }

    /**
     * Every workspace this email can sign into — one row for a standalone client,
     * several for a firm member mirrored across their assigned sub-accounts.
     *
     * @return list<string>
     */
    public static function tenantsFor(string $email): array
    {
        return static::query()
            ->where('email', $email)
            ->orderBy('tenant_id')
            ->pluck('tenant_id')
            ->map(fn ($v): string => (string) $v)
            ->all();
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
     * Map a firm member's email to one of their assigned workspaces, allowing the
     * SAME email to map to several workspaces (unlike link()). Idempotent on the
     * (email, tenant_id) pair. Only the firm-membership flow calls this, for emails
     * whose credentials are centralised on firm_members — so the multi-mapping can
     * never enable a standalone cross-tenant takeover (that path still uses link()).
     */
    public static function linkShared(string $email, string $tenantId): void
    {
        static::query()->updateOrCreate(['email' => $email, 'tenant_id' => $tenantId], []);
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
