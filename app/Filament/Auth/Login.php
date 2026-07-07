<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Models\Tenant;
use App\Models\TenantLogin;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Login page that accepts a plain identifier instead of an email address, so
 * simple demo credentials like "admin" work. The credential is still matched
 * against the users.email column, so seeded users just store "admin" there.
 *
 * Account-based (no-subdomain) tenancy: on the shared /app sign-in we first
 * resolve which workspace the email belongs to (the central login directory) and
 * initialise it, so the password is checked against THAT workspace's users; the
 * workspace is then pinned on the session for subsequent requests. The central
 * /admin panel authenticates landlord users directly, with no workspace.
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        // On the shared customer sign-in (/app) the credential is always the
        // account's EMAIL — the central login directory resolves the workspace
        // from it. Label and validate it as an email so no one types the
        // workspace identifier by mistake. The /admin console keeps a plain
        // identifier for the landlord's "admin" username.
        $isApp = Filament::getCurrentPanel()?->getId() === 'app';

        $field = TextInput::make('email')
            ->label($isApp ? __('app.ui.email_label') : __('app.ui.identifier'))
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);

        if ($isApp) {
            $field->email()
                ->placeholder(__('app.ui.email_placeholder'))
                ->helperText(__('app.ui.email_help'));
        }

        return $field;
    }

    public function mount(): void
    {
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentPanel();

        // A user already signed in AND able to use this panel goes straight in.
        if ($user instanceof FilamentUser && $panel !== null && $user->canAccessPanel($panel)) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        // Everyone else gets the sign-in form — a guest, or a user signed in
        // elsewhere who can't use THIS panel (e.g. the landlord following a
        // client's welcome-email link), who must be able to sign in as the right
        // account rather than be bounced to their own panel. Pre-fill the email
        // when the credentials/welcome email's "Sign in" link carries it
        // (?email=…), so the client only types their password.
        $prefill = $this->prefilledEmail();
        $this->form->fill($prefill !== null ? ['email' => $prefill] : []);
    }

    /** A valid email passed in the query string, to pre-fill the /app sign-in. */
    private function prefilledEmail(): ?string
    {
        if (Filament::getCurrentPanel()?->getId() !== 'app') {
            return null;
        }

        $email = request()->query('email');

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            ? $email
            : null;
    }

    public function authenticate(): ?LoginResponse
    {
        $resolvedWorkspace = false;
        if (Filament::getCurrentPanel()?->getId() === 'app' && tenant() === null) {
            $resolvedWorkspace = $this->initialiseWorkspaceForLogin();
        }

        try {
            $response = parent::authenticate();
        } catch (Throwable $e) {
            // Auth failed (wrong password / MFA). Never leave the worker pinned to
            // the workspace we speculatively initialised to check the password —
            // that would bleed the tenant connection into the rest of the request.
            $this->endSpeculativeWorkspace($resolvedWorkspace);

            throw $e;
        }

        // Pin the resolved workspace so every later request re-initialises it —
        // but only once we've confirmed the authenticated user actually belongs to
        // it (the guard resolved them from this tenant's database, and the central
        // directory agrees). Otherwise drop back to central so a mismatch can never
        // pin a user into a workspace that isn't theirs.
        if ($response !== null && tenant() !== null && $this->authenticatedUserBelongsToTenant()) {
            session()->put('tenant_id', tenant()->getTenantKey());

            return $response;
        }

        $this->endSpeculativeWorkspace($resolvedWorkspace);

        return $response;
    }

    /**
     * Resolve the workspace from the submitted email and initialise it.
     * Returns whether a workspace was initialised.
     */
    private function initialiseWorkspaceForLogin(): bool
    {
        try {
            $email = trim((string) ($this->form->getState()['email'] ?? ''));
        } catch (Throwable) {
            return false; // the form is invalid — parent::authenticate() surfaces it
        }

        $tenantId = $email !== '' ? TenantLogin::tenantFor($email) : null;
        if ($tenantId === null) {
            return false; // unknown email — parent fails auth cleanly (no workspace)
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant === null) {
            return false;
        }

        // A suspended workspace (e.g. unpaid bills) refuses sign-in with a clear
        // message — before any password is checked. Data stays intact throughout.
        if ($tenant->isSuspended()) {
            throw ValidationException::withMessages([
                'data.email' => (string) __('app.suspended.login'),
            ]);
        }

        tenancy()->initialize($tenant);

        return true;
    }

    /** Whether the just-authenticated user's email maps to the current workspace. */
    private function authenticatedUserBelongsToTenant(): bool
    {
        $user = Filament::auth()->user();
        $email = $user !== null ? (string) $user->getAttribute('email') : '';

        return $email !== '' && TenantLogin::tenantFor($email) === tenant()?->getTenantKey();
    }

    /** End a workspace we initialised only to verify the password, on any non-success. */
    private function endSpeculativeWorkspace(bool $resolvedWorkspace): void
    {
        if ($resolvedWorkspace && tenant() !== null) {
            tenancy()->end();
        }
    }
}
