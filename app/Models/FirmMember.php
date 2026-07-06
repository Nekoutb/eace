<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Firm\FirmRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Central. A firm's team member — the SINGLE identity that authenticates the
 * firm's staff. Password + one authenticator secret live here; they are mirrored
 * into each assigned sub-account's tenant user row (FirmMembershipService), so a
 * member signs in once, enters one TOTP, and can hold several client workspaces.
 *
 * @property int $id
 * @property int $firm_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property FirmRole $role
 * @property bool $must_change_password
 * @property bool $is_active
 * @property ?string $app_authentication_secret
 * @property ?array<int, string> $app_authentication_recovery_codes
 * @property ?Carbon $last_login_at
 */
class FirmMember extends CentralModel
{
    protected $guarded = ['id'];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => FirmRole::class,
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * @return HasMany<AccountAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AccountAssignment::class);
    }

    public function isPowerUser(): bool
    {
        return $this->role === FirmRole::PowerUser;
    }

    public function hasTwoFactor(): bool
    {
        return filled($this->app_authentication_secret);
    }

    /** The stored bcrypt hash, for mirroring into a workspace user (never re-hashed). */
    public function passwordHash(): string
    {
        return (string) ($this->getAttributes()['password'] ?? '');
    }
}
