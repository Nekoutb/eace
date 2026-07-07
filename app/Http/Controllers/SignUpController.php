<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PendingSignup;
use App\Services\Tenant\ClientProvisioningException;
use App\Services\Tenant\SelfServiceOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Public self-service onboarding, INSTANT: the customer enters their details and
 * their isolated workspace is provisioned on the spot — the one-time credentials
 * are shown on screen immediately and a welcome email (same credentials, sign-in
 * button) is queued as the courtesy copy. First sign-in enforces 2FA enrolment +
 * a new password, which is also what proves possession of the credentials. No
 * fragile confirmation link in the critical path (the old verify links keep
 * working for signups already in flight). Gated by config('niu.self_service_signup').
 */
class SignUpController
{
    /** Step 1 — the details form. */
    public function show(): View
    {
        $this->ensureEnabled();

        return view('auth.signup');
    }

    /** Step 1 submit — provision INSTANTLY and show the credentials right away. */
    public function register(Request $request, SelfServiceOnboarding $onboarding): RedirectResponse
    {
        $this->ensureEnabled();

        $data = $request->validate([
            // Absent (e.g. an old cached form) safely means a plain company.
            'account_type' => ['nullable', 'in:company,firm'],
            'org_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            // Privacy-notice consent (Loi 2024/017 art. 9) — must be ticked.
            'consent' => ['accepted'],
        ], ['consent.accepted' => (string) __('app.signup.consent_required')]);

        $email = trim($data['email']);

        if (! PendingSignup::emailAvailable($email)) {
            return back()->withInput()->withErrors(['email' => (string) __('app.signup.email_taken')]);
        }

        try {
            $client = $onboarding->provisionNow(
                $data['org_name'],
                $data['name'],
                $email,
                (string) ($data['account_type'] ?? 'company'),
            );
        } catch (ClientProvisioningException) {
            return back()->withInput()->withErrors(['email' => (string) __('app.signup.email_taken')]);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['email' => (string) __('app.client.provision_error')]);
        }

        // Record the accepted privacy notice (art. 9) on the workspace itself.
        $client->tenant->update([
            'consent_version' => (string) config('niu.data_protection.policy_version', '1.0'),
            'consent_accepted_at' => now()->toDateTimeString(),
        ]);

        return redirect()->route('signup.done')->with([
            'done_email' => $client->adminEmail,
            'done_password' => (string) $client->temporaryPassword,
            'done_login' => $client->loginUrl,
        ]);
    }

    /** Step 2 — "check your email". */
    public function sent(Request $request): View|RedirectResponse
    {
        $this->ensureEnabled();

        $email = $request->session()->get('signup_email');
        if (! is_string($email) || $email === '') {
            return redirect()->route('signup');
        }

        return view('auth.signup-sent', [
            'email' => $email,
            'hours' => (int) config('niu.pending_signup_expiry_hours', 24),
        ]);
    }

    /** Step 2 — re-send the verification link. */
    public function resend(Request $request, SelfServiceOnboarding $onboarding): RedirectResponse
    {
        $this->ensureEnabled();

        $email = $request->session()->get('signup_email');
        if (is_string($email) && $email !== '') {
            $pending = PendingSignup::query()
                ->where('email', $email)
                ->where('expires_at', '>', now())
                ->latest('id')
                ->first();

            if ($pending !== null) {
                $onboarding->resend($pending);
            }
        }

        return redirect()->route('signup.sent')->with('status', (string) __('app.signup.resend_done'));
    }

    /** Email-confirmation link — provision the workspace, then show the result. */
    public function verify(string $token, Request $request, SelfServiceOnboarding $onboarding): RedirectResponse|View
    {
        $this->ensureEnabled();

        try {
            $client = $onboarding->completeFromToken($token);
        } catch (Throwable $e) {
            report($e);

            return view('auth.signup-expired');
        }

        if ($client === null) {
            return view('auth.signup-expired');
        }

        $request->session()->forget('signup_email');

        return redirect()->route('signup.done')->with([
            'done_email' => $client->adminEmail,
            'done_password' => (string) $client->temporaryPassword,
            'done_login' => $client->loginUrl,
        ]);
    }

    /** Step 3 — workspace ready (one-time password shown once). */
    public function done(Request $request): View|RedirectResponse
    {
        $this->ensureEnabled();

        if (! $request->session()->has('done_email')) {
            return redirect('/app/login');
        }

        return view('auth.signup-done', [
            'email' => (string) $request->session()->get('done_email'),
            'password' => (string) $request->session()->get('done_password'),
            'loginUrl' => (string) $request->session()->get('done_login'),
        ]);
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) config('niu.self_service_signup'), 404);
    }
}
