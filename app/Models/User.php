<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Auth\UserRole;
use App\Observers\TenantLoginObserver;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Serves both panels: on the central admin panel it resolves against the central
 * users table; on a tenant panel (tenancy initialised by domain) the connection
 * is swapped so it resolves against that tenant's users table. The role /
 * must_change_password / last_login_at columns only exist in the tenant table —
 * on the central connection they are simply absent (role() reads as null).
 *
 * @property ?UserRole $role
 * @property bool $must_change_password
 * @property ?Carbon $last_login_at
 * @property ?string $app_authentication_secret
 * @property ?array<int, string> $app_authentication_recovery_codes
 */
#[ObservedBy([TenantLoginObserver::class])]
#[Fillable(['name', 'email', 'password', 'role', 'must_change_password', 'last_login_at'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Explicit panel boundary (defence in depth, beyond the domain/connection
     * split): central users — whose table has no role column — may reach only
     * the central /admin panel; tenant users may reach only the /app panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'app' ? ! $this->isCentralUser() : $this->isCentralUser();
    }

    /** A central (landlord) user, identified by the absence of the tenant-only role column. */
    public function isCentralUser(): bool
    {
        return ! array_key_exists('role', $this->getAttributes());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            // Two-factor (TOTP) — encrypted at rest with the app key.
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    // --- Two-factor authentication (Filament app/TOTP MFA) ------------------

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    /** The label shown beside the brand in the user's authenticator app. */
    public function getAppAuthenticationHolderName(): string
    {
        return (string) ($this->email ?? $this->name);
    }

    /**
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  ?array<string>  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    /** Tenant admins manage staff and settings; recorders/settlers only do the work. */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** May this user record invoices? Admins and recorders can. */
    public function canRecord(): bool
    {
        return $this->role?->canRecord() ?? false;
    }

    /** May this user settle invoices? Admins and settlers can. */
    public function canSettle(): bool
    {
        return $this->role?->canSettle() ?? false;
    }

    /**
     * The Compliance Engine stage this user should land on. A settle-only user
     * opens on settlement; everyone else (admins, recorders) opens on recording.
     */
    public function defaultStage(): string
    {
        return ! $this->canRecord() && $this->canSettle() ? 'settlement' : 'recording';
    }

    /**
     * Whether removing or demoting this user would leave the tenant with no
     * admin. Counts admins OTHER than this one, so an admin demoting or deleting
     * themselves is correctly caught when they are the only admin.
     */
    public function isLastAdmin(): bool
    {
        return $this->isAdmin()
            && static::query()
                ->where('role', UserRole::Admin->value)
                ->whereKeyNot($this->getKey())
                ->doesntExist();
    }
}
