<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Mail\NewWorkspaceAdminNotice;
use App\Mail\WorkspaceCredentialsMail;
use App\Mail\WorkspaceVerifyMail;
use App\Models\PendingSignup;
use App\Models\User;
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
    public function __construct(private readonly ClientProvisioningService $provisioning) {}

    /**
     * Record a pending sign-up and email the verification link. The caller has
     * already validated that the email is available (PendingSignup::emailAvailable).
     */
    public function startPending(string $orgName, string $name, string $email): PendingSignup
    {
        $pending = PendingSignup::start($orgName, $name, $email);

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

        $client = $this->provisioning->provision(
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
