<?php

declare(strict_types=1);

namespace App\Services\Firm;

use App\Domain\Auth\UserRole;
use App\Domain\Firm\FirmRole;
use App\Models\AccountAssignment;
use App\Models\Firm;
use App\Models\FirmMember;
use App\Models\Tenant;
use App\Models\TenantLogin;
use App\Models\User;
use App\Services\Tenant\ClientProvisioningService;
use App\Support\TempPassword;

/**
 * The accounting-firm umbrella: firms, their members, and the client sub-accounts
 * they are assigned to. One member = one central identity (FirmMember) whose
 * password + one authenticator secret are MIRRORED into each assigned sub-account's
 * tenant user row — so the member signs in once, with one TOTP, and can hold
 * several workspaces. Every sub-account keeps its own isolated database; this layer
 * is pure central metadata plus the credential sync that makes single sign-in work.
 */
final class FirmMembershipService
{
    public function __construct(private ClientProvisioningService $provisioning) {}

    public function createFirm(string $name, int $maxSubAccounts = 25, int $defaultQuotaMb = 1024): Firm
    {
        return Firm::query()->create([
            'name' => trim($name),
            'max_sub_accounts' => max(1, $maxSubAccounts),
            'default_quota_mb' => max(0, $defaultQuotaMb),
        ]);
    }

    /**
     * Add a person to a firm. The email must be globally free — not another member
     * and not a standalone client login — so a firm identity and a standalone
     * client identity can never collide (which keeps login routing unambiguous).
     * A one-time temporary password is generated unless one is supplied.
     */
    public function addMember(Firm $firm, string $name, string $email, FirmRole $role = FirmRole::Member, ?string $password = null): AddedMember
    {
        $email = mb_strtolower(trim($email));

        if (FirmMember::query()->where('email', $email)->exists()
            || TenantLogin::query()->where('email', $email)->exists()) {
            throw FirmException::emailTaken($email);
        }

        $usesTemporary = $password === null;
        $effectivePassword = $password ?? TempPassword::generate();

        $member = FirmMember::query()->create([
            'firm_id' => $firm->getKey(),
            'name' => trim($name),
            'email' => $email,
            'password' => $effectivePassword, // hashed by the cast
            'role' => $role->value,
            'must_change_password' => $usesTemporary,
            'is_active' => true,
        ]);

        return new AddedMember($member, $usesTemporary ? $effectivePassword : null);
    }

    /**
     * Create a client sub-account under a firm (an isolated workspace). Enforces
     * the firm's plan cap. No standalone admin is seeded — members are attached
     * with assign().
     */
    public function createSubAccount(Firm $firm, string $clientName, ?string $niu = null, ?int $quotaMb = null): Tenant
    {
        if (! $firm->canAddSubAccount()) {
            throw FirmException::subAccountCapReached($firm->max_sub_accounts);
        }

        return $this->provisioning->provisionSubAccount(
            $firm,
            $clientName,
            $niu,
            $quotaMb ?? $firm->default_quota_mb,
        );
    }

    /**
     * Assign a member to one of their firm's sub-accounts with a role inside that
     * workspace, and mirror them in as a real tenant user (credentials synced).
     */
    public function assign(FirmMember $member, string $tenantId, UserRole $accountRole = UserRole::Admin, ?int $assignedBy = null): AccountAssignment
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        if ((int) $tenant->firm_id !== (int) $member->firm_id) {
            throw FirmException::crossFirmAssignment();
        }

        $assignment = AccountAssignment::query()->updateOrCreate(
            ['firm_member_id' => $member->getKey(), 'tenant_id' => $tenantId],
            ['account_role' => $accountRole->value, 'assigned_by' => $assignedBy],
        );

        $this->mirrorIntoTenant($member, $tenant, $accountRole);

        return $assignment;
    }

    /** Remove a member's access to a sub-account: drop the assignment + their tenant user. */
    public function unassign(FirmMember $member, string $tenantId): void
    {
        AccountAssignment::query()
            ->where('firm_member_id', $member->getKey())
            ->where('tenant_id', $tenantId)
            ->delete();

        $tenant = Tenant::query()->find($tenantId);
        $tenant?->run(function () use ($member): void {
            // Delete the INSTANCE (not a mass query delete) so the observer fires
            // and unlinks the directory row.
            User::query()->where('email', $member->email)->get()->each->delete();
        });

        // Explicit backstop: ensure the (email, tenant) directory row is gone even
        // if no user row existed for it.
        TenantLogin::unlink($member->email, $tenantId);
    }

    /**
     * Propagate the member's central credentials (password, must-change flag, one
     * authenticator secret + recovery codes) to every workspace they hold. Call
     * after a password change or 2FA (re)enrolment so single sign-in stays true.
     */
    public function syncCredentials(FirmMember $member): void
    {
        foreach ($member->assignments()->get() as $assignment) {
            $tenant = Tenant::query()->find($assignment->tenant_id);
            $tenant?->run(function () use ($member): void {
                $user = User::query()->where('email', $member->email)->first();
                // saveQuietly: these rows are already directory-linked, and firing
                // the credential-sync observer here would only re-push the very
                // values we are fanning out.
                $user?->forceFill([
                    'password' => $member->passwordHash(),
                    'must_change_password' => $member->must_change_password,
                    'app_authentication_secret' => $member->app_authentication_secret,
                    'app_authentication_recovery_codes' => $member->app_authentication_recovery_codes,
                ])->saveQuietly();
            });
        }
    }

    /** Delete a client sub-account: its assignments, directory rows, then its database. */
    public function deleteSubAccount(string $tenantId): void
    {
        AccountAssignment::query()->where('tenant_id', $tenantId)->delete();
        TenantLogin::query()->where('tenant_id', $tenantId)->delete();
        Tenant::query()->find($tenantId)?->delete(); // fires DeleteDatabase
    }

    /** Delete a firm — refused while it still owns sub-accounts (delete those first). */
    public function deleteFirm(Firm $firm): void
    {
        if ($firm->subAccounts()->exists()) {
            throw FirmException::firmHasSubAccounts();
        }

        $firm->delete(); // cascades firm_members + their account_assignments
    }

    /**
     * Create or update the member's user row inside a workspace, carrying the
     * central credentials verbatim (the bcrypt hash is copied, never re-hashed;
     * the TOTP secret is the SAME plaintext, re-encrypted per row → one
     * authenticator entry for the person across all their workspaces).
     */
    private function mirrorIntoTenant(FirmMember $member, Tenant $tenant, UserRole $role): void
    {
        $tenant->run(function () use ($member, $role): void {
            $user = User::query()->firstOrNew(['email' => $member->email]);
            $user->forceFill([
                'name' => $member->name,
                'password' => $member->passwordHash(),
                'role' => $role->value,
                'must_change_password' => $member->must_change_password,
                'app_authentication_secret' => $member->app_authentication_secret,
                'app_authentication_recovery_codes' => $member->app_authentication_recovery_codes,
            ])->save();
        });
    }
}
