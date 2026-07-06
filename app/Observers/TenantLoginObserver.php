<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\FirmMember;
use App\Models\TenantLogin;
use App\Models\User;

/**
 * Keeps the central login directory (email → workspace) in sync with the tenant
 * users, so the shared no-subdomain sign-in can always resolve a user to their
 * workspace. Only acts on TENANT users (a save/delete inside an initialised
 * tenant); central/landlord users are skipped — they sign in to /admin directly.
 */
final class TenantLoginObserver
{
    public function saved(User $user): void
    {
        if (! $this->isTenantUser($user)) {
            return;
        }

        $tenantId = (string) tenant()->getTenantKey();

        // On an email change, drop the previous email's directory entry first —
        // scoped to this workspace so it can never touch another tenant's entry.
        $original = $user->getOriginal('email');
        if (is_string($original) && $original !== '' && $original !== $user->email) {
            TenantLogin::unlink($original, $tenantId);
        }

        // A firm member is mirrored into several sub-accounts, so their email maps
        // to many workspaces (linkShared). A standalone client's email stays
        // exclusive to its one workspace (link) — the cross-tenant-takeover guard.
        if (FirmMember::query()->where('email', $user->email)->exists()) {
            TenantLogin::linkShared((string) $user->email, $tenantId);
        } else {
            TenantLogin::link((string) $user->email, $tenantId);
        }
    }

    public function deleted(User $user): void
    {
        if (! $this->isTenantUser($user)) {
            return;
        }

        TenantLogin::unlink((string) $user->email, (string) tenant()->getTenantKey());
    }

    /** A real tenant user — saved inside an initialised tenant, with a role column. */
    private function isTenantUser(User $user): bool
    {
        return tenant() !== null && ! $user->isCentralUser();
    }
}
