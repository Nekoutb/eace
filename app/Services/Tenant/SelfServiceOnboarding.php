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
 * Self-service onboarding, built for the moment a client signs up IN FRONT OF
 * someone: the workspace is provisioned INSTANTLY on submission and the one-time
 * credentials are shown on screen right away — the welcome email (same
 * credentials, sign-in button) is QUEUED as the courtesy copy, so slow SMTP can
 * never stall or embarrass the flow. There is no fragile single-use link in the
 * critical path any more: possessing the one-time password (on screen or from the
 * email) is what proves the sign-up, and first sign-in forces 2FA enrolment + a
 * new password.
 *
 * The old verify-first path (completeFromToken) is kept working for any pending
 * sign-ups already in flight.
 */
final class SelfServiceOnboarding
{
    public function __construct(
        private readonly ClientProvisioningService $provisioning,
        private readonly FirmMembershipService $firms,
    ) {}

    /**
     * Provision instantly: a standalone company workspace, or an accounting-firm
     * umbrella + its home workspace with the signer as power user. Queues the
     * welcome email + operator notice; returns the credentials for the screen.
     */
    public function provisionNow(string $orgName, string $name, string $email, string $accountType = 'company'): ProvisionedClient
    {
        $client = $accountType === 'firm'
            ? $this->provisionFirmAccount($orgName, $name, $email)
            : $this->provisioning->provision(
                name: $orgName,
                adminName: $name,
                adminEmail: $email,
                allocatedSpaceMb: (int) config('niu.trial_allocated_space_mb', 250),
                // password: null → a one-time password, changed on first sign-in.
            );

        $this->queueWelcome($client, $name, $orgName);
        $this->queueOperatorNotice($client, $orgName);

        return $client;
    }

    /**
     * LEGACY (verify-first) — kept so confirmation links already in inboxes keep
     * working. Confirms a token and provisions. Returns null if the token is
     * unknown, already used, or expired.
     */
    public function completeFromToken(string $token): ?ProvisionedClient
    {
        $pending = PendingSignup::consumeValidByToken($token);

        if ($pending === null) {
            return null;
        }

        $client = $pending->isFirm()
            ? $this->provisionFirmAccount($pending->org_name, $pending->name, $pending->email)
            : $this->provisioning->provision(
                name: $pending->org_name,
                adminName: $pending->name,
                adminEmail: $pending->email,
                allocatedSpaceMb: (int) config('niu.trial_allocated_space_mb', 250),
            );

        // This path DID prove the email — mark it verified.
        $client->tenant->run(function () use ($pending): void {
            User::query()->where('email', $pending->email)->update(['email_verified_at' => now()]);
        });

        $this->queueWelcome($client, $pending->name, $pending->org_name);
        $this->queueOperatorNotice($client, $pending->org_name);

        return $client;
    }

    /** LEGACY — re-send the verification link for an existing pending sign-up. */
    public function resend(PendingSignup $pending): void
    {
        Mail::to($pending->email)->locale(app()->getLocale())->send(new WorkspaceVerifyMail(
            name: $pending->name,
            orgName: $pending->org_name,
            verifyUrl: route('signup.verify', ['token' => $pending->token]),
            expiryHours: (int) config('niu.pending_signup_expiry_hours', 24),
        ));
    }

    /**
     * A FIRM sign-up: the umbrella (trial plan caps), the signer as POWER USER
     * (one-time password, forced change on first sign-in), and the firm's own home
     * workspace with the power user assigned as its admin. They then create client
     * sub-accounts themselves from the in-app firm console.
     */
    private function provisionFirmAccount(string $orgName, string $name, string $email): ProvisionedClient
    {
        $firm = $this->firms->createFirm(
            $orgName,
            maxSubAccounts: (int) config('niu.firm_trial_max_sub_accounts', 5),
            defaultQuotaMb: (int) config('niu.trial_allocated_space_mb', 250),
        );

        try {
            $added = $this->firms->addMember($firm, $name, $email, FirmRole::PowerUser);

            $home = $this->firms->createSubAccount($firm, $orgName);
            $this->firms->assign($added->member, (string) $home->getTenantKey(), UserRole::Admin);

            return new ProvisionedClient(
                tenant: $home,
                loginUrl: rtrim((string) config('app.url'), '/').'/app/login',
                adminEmail: $email,
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

    /**
     * The welcome email carrying the credentials and the sign-in button (email
     * pre-filled). QUEUED so a slow mail server can never stall the sign-up screen
     * — the same details are already showing there.
     */
    private function queueWelcome(ProvisionedClient $client, string $name, string $orgName): void
    {
        try {
            Mail::to($client->adminEmail)->locale(app()->getLocale())->queue(new WorkspaceCredentialsMail(
                name: $name,
                orgName: $orgName,
                email: $client->adminEmail,
                temporaryPassword: (string) $client->temporaryPassword,
                loginUrl: $client->loginUrl,
            ));
        } catch (\Throwable $e) {
            report($e); // the screen already carries the credentials
        }
    }

    private function queueOperatorNotice(ProvisionedClient $client, string $orgName): void
    {
        $notify = config('niu.signup_admin_notify');
        if (! is_string($notify) || $notify === '') {
            return;
        }

        try {
            Mail::to($notify)->queue(new NewWorkspaceAdminNotice(
                orgName: $orgName,
                email: $client->adminEmail,
                tenantId: (string) $client->tenant->getTenantKey(),
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
