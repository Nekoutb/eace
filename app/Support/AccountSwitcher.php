<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AccountAssignment;
use App\Models\DataAccessLog;
use App\Models\Firm;
use App\Models\FirmMember;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * The accounting-firm workspace switcher: lets a signed-in firm member move
 * between the client sub-accounts they are assigned to, WITHOUT signing in again.
 *
 * A switch is a server-side context swap — the target is always re-verified
 * against account_assignments (never trusted from the request), the member is
 * re-logged into that workspace's mirrored user, the session password-hash is
 * refreshed (or AuthenticateSession would bounce them), and the move is audited.
 * Modelled on read-only impersonation, but this is the member's OWN access, so it
 * is full read/write inside each assigned workspace.
 */
final class AccountSwitcher
{
    /** The firm member behind the signed-in workspace user, or null (a standalone client). */
    public static function memberFor(?User $user): ?FirmMember
    {
        $email = $user?->getAttribute('email');
        if (! is_string($email) || $email === '') {
            return null;
        }

        return FirmMember::query()->where('email', $email)->where('is_active', true)->first();
    }

    public static function isFirmMember(?User $user): bool
    {
        return self::memberFor($user) !== null;
    }

    /**
     * The firm the signed-in user manages, or null — they must be an active POWER
     * USER of an active firm. Gates the in-app firm console.
     */
    public static function managedFirm(?User $user): ?Firm
    {
        $member = self::memberFor($user);
        if ($member === null || ! $member->isPowerUser()) {
            return null;
        }

        $firm = $member->firm()->first();

        return $firm !== null && $firm->is_active ? $firm : null;
    }

    /**
     * The workspaces the signed-in member may switch to, each with its display name
     * and whether it is the current one. Empty for a standalone client.
     *
     * @return list<array{id: string, name: string, current: bool}>
     */
    public static function workspacesFor(?User $user): array
    {
        $member = self::memberFor($user);
        if ($member === null) {
            return [];
        }

        $current = (string) (tenant()?->getTenantKey() ?? session('tenant_id') ?? '');

        $assignments = AccountAssignment::query()
            ->where('firm_member_id', $member->getKey())
            ->pluck('tenant_id')
            ->all();

        if ($assignments === []) {
            return [];
        }

        // cursor(): iteration typed to our Tenant (stancl's TenantCollection erases
        // the model type for static analysis). Suspended workspaces are simply not
        // offered.
        $names = [];
        foreach (Tenant::query()->whereIn('id', $assignments)->cursor() as $t) {
            if ($t->isSuspended()) {
                continue;
            }
            $names[(string) $t->getTenantKey()] = (string) ($t->name ?? $t->getTenantKey());
        }
        $assignments = array_values(array_intersect($assignments, array_keys($names)));

        $out = [];
        foreach ($assignments as $id) {
            $id = (string) $id;
            $out[] = [
                'id' => $id,
                'name' => $names[$id] ?? $id,
                'current' => $id === $current,
            ];
        }

        usort($out, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * Switch the signed-in member to another of THEIR assigned workspaces. Returns
     * false (changing nothing) if the current user isn't a firm member, isn't
     * assigned to the target, or the target is gone.
     */
    public static function switchTo(string $tenantId): bool
    {
        // Refusals are rare and always confusing for the member — log WHY, with
        // enough context to diagnose from the server without a repro session.
        $refused = static function (string $reason, array $context = []) use ($tenantId): bool {
            Log::warning('workspace switch refused', array_merge([
                'reason' => $reason,
                'target' => $tenantId,
                'auth_id' => Auth::id(),
                'auth_class' => Auth::user() !== null ? Auth::user()::class : null,
                'auth_email' => (string) Auth::user()?->getAttribute('email'),
                'session_tenant' => session('tenant_id'),
                'tenancy_initialized' => (bool) tenancy()->initialized,
            ], $context));

            return false;
        };

        // A badged-in operator is a read-only VIEWER of one workspace — switching
        // would silently swap the audited identity. Exit impersonation first.
        if (Impersonation::isActive()) {
            return $refused('impersonation active — read-only view cannot switch workspaces');
        }

        $current = Auth::user();
        $member = self::memberFor($current instanceof User ? $current : null);
        if ($member === null) {
            return $refused('no active firm member matches the signed-in user');
        }

        $assigned = AccountAssignment::query()
            ->where('firm_member_id', $member->getKey())
            ->where('tenant_id', $tenantId)
            ->exists();
        if (! $assigned) {
            // never trust the request — only real assignments switch
            return $refused('member is not assigned to the target', ['member_id' => $member->getKey()]);
        }

        $target = Tenant::query()->find($tenantId);
        if ($target === null || $target->isSuspended()) {
            return $refused($target === null ? 'target workspace does not exist' : 'target workspace is suspended');
        }

        DataAccessLog::record('workspace_switch', 'tenant:'.$tenantId, 'member '.$member->email);

        if (tenant() !== null) {
            tenancy()->end();
        }
        tenancy()->initialize($target);

        $user = User::query()->where('email', $member->email)->first();
        if (! $user instanceof User) {
            tenancy()->end();

            return $refused('no mirrored user for the member inside the target workspace');
        }

        Auth::guard('web')->login($user);
        // AuthenticateSession compares the stored hash to the current user each
        // request and logs out on a mismatch — update it after swapping users.
        session()->put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());
        session()->put('tenant_id', $tenantId);

        return true;
    }
}
