<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Auth\UserRole;
use App\Models\AccountAssignment;
use App\Models\FirmMember;
use App\Models\TenantLogin;
use App\Models\User;

/**
 * Keeps the central login directory (email → workspace) in sync with the tenant
 * users, so the shared no-subdomain sign-in can always resolve a user to their
 * workspace. Only acts on TENANT users (a save/delete inside an initialised
 * tenant); central/landlord users are skipped — they sign in to /admin directly.
 *
 * For FIRM MEMBERS it also keeps account_assignments consistent: workspace access
 * granted through ANY path (the firm console's give-access, or the workspace's
 * own Users screen) records the assignment that drives the member's workspace
 * switcher — otherwise a member could hold access the switcher can't see.
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
        $member = FirmMember::query()->where('email', $user->email)->first();
        if ($member !== null) {
            TenantLogin::linkShared((string) $user->email, $tenantId);
            $this->syncAssignment($member, $tenantId, $user);
        } else {
            TenantLogin::link((string) $user->email, $tenantId);
        }
    }

    public function deleted(User $user): void
    {
        if (! $this->isTenantUser($user)) {
            return;
        }

        $tenantId = (string) tenant()->getTenantKey();
        TenantLogin::unlink((string) $user->email, $tenantId);

        // Losing the workspace user also ends the member's assignment to it, so
        // the switcher and the firm console stay truthful.
        $member = FirmMember::query()->where('email', $user->email)->first();
        if ($member !== null) {
            AccountAssignment::query()
                ->where('firm_member_id', $member->getKey())
                ->where('tenant_id', $tenantId)
                ->delete();
        }
    }

    /**
     * Record (or update the role of) the member's assignment to this workspace —
     * only when the workspace belongs to the member's own firm; a firm member
     * user-row in an unrelated workspace never creates cross-firm access.
     */
    private function syncAssignment(FirmMember $member, string $tenantId, User $user): void
    {
        $tenantFirmId = tenant()?->getAttribute('firm_id');
        if ((int) $tenantFirmId !== (int) $member->firm_id) {
            return;
        }

        AccountAssignment::query()->updateOrCreate(
            ['firm_member_id' => $member->getKey(), 'tenant_id' => $tenantId],
            ['account_role' => $user->role->value ?? UserRole::Recorder->value],
        );
    }

    /** A real tenant user — saved inside an initialised tenant, with a role column. */
    private function isTenantUser(User $user): bool
    {
        return tenant() !== null && ! $user->isCentralUser();
    }
}
