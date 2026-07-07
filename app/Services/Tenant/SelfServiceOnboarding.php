<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Domain\Auth\UserRole;
use App\Domain\Firm\FirmRole;
use App\Mail\NewWorkspaceAdminNotice;
use App\Mail\WorkspaceCredentialsMail;
use App\Mail\WorkspaceVerifyMail;
use App\Models\PendingSignup;
use App\Models\User;
use App\Services\Firm\FirmMembershipService;
use Illuminate\Support\Facades\Mail;

/**
 * Drives verify-first self-service onboarding: a prospective customer's sign-up
 * is held as a PendingSignup until they confirm their email, and only then is the
 * real workspace provisioned and its one-time credentials emailed.
 *
 * The email round-trip is the safeguard — no tenant database is created for an
 * unconfirmed (abandoned or fake) sign-up.
 */
final class SelfServiceOnboarding
{
    public function __construct(
        private readonly ClientProvisioningService $provisioning,
        private readonly FirmMembershipService $firms,
    ) {}

    /**
     * Record a pending sign-up and email the verification link. The caller has
     * already validated that the email is available (PendingSignup::emailAvailable).
     */
    public function startPending(string $orgName, string $name, string $email, string $accountType = 'company'): PendingSignup
    {
        $pending = PendingSignup::start($orgName, $name, $email, $accountType);

        $this->sendVerification($pending);

        return $pending;
    }

    /** Re-send the verification link for an existing pending sign-up. */
    public function resend(PendingSignup $pending): void
    {
        $this->sendVerification($pending);
    }

    /**
     * Confirm a verification token and provision the workspace. Returns the
     * provisioned client (carrying the one-time password) or null if the token is
     * unknown, already used, or expired.
     */
    public function completeFromToken(string $token): ?ProvisionedClient
    {
        $pending = PendingSignup::consumeValidByToken($token);

        if ($pending === null) {
            return null;
        }

        $client = $pending->isFirm()
            ? $this->provisionFirm($pending)
            : $this->provisioning->provision(
                name: $pending->org_name,
                adminName: $pending->name,
                adminEmail: $pending->email,
                allocatedSpaceMb: (int) config('niu.trial_allocated_space_mb', 250),
                // password: null → a one-time password is generated and the user must
                // change it on first sign-in (the same path as admin onboarding).
            );

        // The email was proven by the verification step — mark it verified.
        $client->tenant->run(function () use ($pending): void {
            User::query()->where('email', $pending->email)->update(['email_verified_at' => now()]);
        });

        Mail::to($pending->email)->locale(app()->getLocale())->send(new WorkspaceCredentialsMail(
            name: $pending->name,
            orgName: $pending->org_name,
            email: $pending->email,
            temporaryPassword: (string) $client->temporaryPassword,
            loginUrl: $client->loginUrl,
        ));

        $notify = config('niu.signup_admin_notify');
        if (is_string($notify) && $notify !== '') {
            Mail::to($notify)->send(new NewWorkspaceAdminNotice(
                orgName: $pending->org_name,
                email: $pending->email,
                tenantId: (string) $client->tenant->getTenantKey(),
            ));
        }

        return $client;
    }

    /**
     * A FIRM sign-up: create the umbrella (trial plan caps), the signer as its
     * POWER USER (one-time password, forced change on first sign-in), and the
     * firm's own home workspace with the power user assigned as its admin. They
     * then create client sub-accounts themselves from the in-app firm console.
     */
    private function provisionFirm(PendingSignup $pending): ProvisionedClient
    {
        $firm = $this->firms->createFirm(
            $pending->org_name,
            maxSubAccounts: (int) config('niu.firm_trial_max_sub_accounts', 5),
            defaultQuotaMb: (int) config('niu.trial_allocated_space_mb', 250),
        );

        try {
            $added = $this->firms->addMember($firm, $pending->name, $pending->email, FirmRole::PowerUser);

            $home = $this->firms->createSubAccount($firm, $pending->org_name);
            $this->firms->assign($added->member, (string) $home->getTenantKey(), UserRole::Admin);

            return new ProvisionedClient(
                tenant: $home,
                loginUrl: rtrim((string) config('app.url'), '/').'/app/login',
                adminEmail: $pending->email,
                temporaryPassword: $added->temporaryPassword,
            );
        } catch (\Throwable $e) {
            // Leave nothing behind on a failed firm sign-up: sub-accounts (rare —
            // only the home workspace could exist here), members, then the firm.
            foreach ($firm->subAccounts()->pluck('id') as $tenantId) {
                $this->firms->deleteSubAccount((string) $tenantId);
            }
            $firm->delete();

            throw $e;
        }
    }

    private function sendVerification(PendingSignup $pending): void
    {
        Mail::to($pending->email)->locale(app()->getLocale())->send(new WorkspaceVerifyMail(
            name: $pending->name,
            orgName: $pending->org_name,
            verifyUrl: route('signup.verify', ['token' => $pending->token]),
            expiryHours: (int) config('niu.pending_signup_expiry_hours', 24),
        ));
    }
}
