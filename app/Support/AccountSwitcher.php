<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AccountAssignment;
use App\Models\DataAccessLog;
use App\Models\FirmMember;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

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
        // the model type for static analysis).
        $names = [];
        foreach (Tenant::query()->whereIn('id', $assignments)->cursor() as $t) {
            $names[(string) $t->getTenantKey()] = (string) ($t->name ?? $t->getTenantKey());
        }

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
        $current = Auth::user();
        $member = self::memberFor($current instanceof User ? $current : null);
        if ($member === null) {
            return false;
        }

        $assigned = AccountAssignment::query()
            ->where('firm_member_id', $member->getKey())
            ->where('tenant_id', $tenantId)
            ->exists();
        if (! $assigned) {
            return false; // never trust the request — only real assignments switch
        }

        $target = Tenant::query()->find($tenantId);
        if ($target === null) {
            return false;
        }

        DataAccessLog::record('workspace_switch', 'tenant:'.$tenantId, 'member '.$member->email);

        if (tenant() !== null) {
            tenancy()->end();
        }
        tenancy()->initialize($target);

        $user = User::query()->where('email', $member->email)->first();
        if (! $user instanceof User) {
            tenancy()->end();

            return false;
        }

        Auth::guard('web')->login($user);
        // AuthenticateSession compares the stored hash to the current user each
        // request and logs out on a mismatch — update it after swapping users.
        session()->put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());
        session()->put('tenant_id', $tenantId);

        return true;
    }
}
