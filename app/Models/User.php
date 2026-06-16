<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Auth\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
 */
#[Fillable(['name', 'email', 'password', 'role', 'must_change_password', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
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
        ];
    }

    /** Tenant admins manage staff and settings; operators only do the work. */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
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
