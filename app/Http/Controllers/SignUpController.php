<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PendingSignup;
use App\Services\Tenant\SelfServiceOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Public, verify-first self-service onboarding. A prospective customer enters
 * their details (step 1), confirms their email (step 2), and only then is their
 * isolated workspace created with a one-time password emailed to them (step 3).
 * They land as the workspace Administrator. Gated by config('niu.self_service_signup').
 */
class SignUpController
{
    /** Step 1 — the details form. */
    public function show(): View
    {
        $this->ensureEnabled();

        return view('auth.signup');
    }

    /** Step 1 submit — hold a pending sign-up and email the verification link. */
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

        $onboarding->startPending($data['org_name'], $data['name'], $email, (string) ($data['account_type'] ?? 'company'));

        return redirect()->route('signup.sent')->with('signup_email', $email);
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
