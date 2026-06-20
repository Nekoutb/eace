<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Models\Tenant;
use App\Models\TenantLogin;
use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Facades\Filament;
use SensitiveParameter;

/**
 * The second half of account-based password reset: the reset link carries the
 * user's email, so before the token is validated and the new password written we
 * resolve and initialise that email's workspace — the token and the user both
 * live in the tenant database. The central /admin console resets landlord users
 * directly. Pairs with App\Filament\Auth\RequestPasswordReset.
 */
class ResetPassword extends BaseResetPassword
{
    public function mount(?string $email = null, #[SensitiveParameter] ?string $token = null): void
    {
        $this->initialiseWorkspaceFor($email ?? request()->query('email'));

        parent::mount($email, $token);
    }

    public function resetPassword(): ?PasswordResetResponse
    {
        $this->initialiseWorkspaceFor($this->email);

        return parent::resetPassword();
    }

    private function initialiseWorkspaceFor(mixed $email): void
    {
        if (Filament::getCurrentPanel()?->getId() !== 'app' || tenant() !== null) {
            return;
        }

        $email = is_string($email) ? trim($email) : '';
        $tenantId = $email !== '' ? TenantLogin::tenantFor($email) : null;
        if ($tenantId === null) {
            return;
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant !== null) {
            tenancy()->initialize($tenant);
        }
    }
}
