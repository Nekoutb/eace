<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Models\Tenant;
use App\Models\TenantLogin;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
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
        return TextInput::make('email')
            ->label(__('app.ui.identifier'))
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
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
