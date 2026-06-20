<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Models\Tenant;
use App\Models\TenantLogin;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Facades\Filament;
use Throwable;

/**
 * Account-based (no-subdomain) password reset. On the shared /app sign-in the
 * workspace a reset email belongs to is resolved from the submitted address (the
 * central login directory) and initialised first, so the reset link — and the
 * token behind it — are issued against THAT workspace's database. The central
 * /admin console resets landlord users directly, with no workspace.
 *
 * Mirrors App\Filament\Auth\Login, which resolves the workspace at sign-in.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function request(): void
    {
        $this->initialiseWorkspaceFromForm();

        parent::request();
    }

    private function initialiseWorkspaceFromForm(): void
    {
        if (Filament::getCurrentPanel()?->getId() !== 'app' || tenant() !== null) {
            return;
        }

        try {
            $email = trim((string) ($this->form->getState()['email'] ?? ''));
        } catch (Throwable) {
            return;
        }

        $tenantId = $email !== '' ? TenantLogin::tenantFor($email) : null;
        if ($tenantId === null) {
            return; // unknown / central email — the broker reports "no link sent"
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant !== null) {
            tenancy()->initialize($tenant);
        }
    }
}
