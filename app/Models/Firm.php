<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Central. An accounting firm — the umbrella account. It owns many sub-accounts
 * (client workspaces, via tenants.firm_id) and many members (its team). Holds no
 * compliance evidence itself: that lives in each sub-account's own tenant database.
 *
 * @property int $id
 * @property string $name
 * @property int $max_sub_accounts
 * @property int $default_quota_mb
 * @property bool $is_active
 */
class Firm extends CentralModel
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_sub_accounts' => 'integer',
            'default_quota_mb' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FirmMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(FirmMember::class);
    }

    /**
     * @return HasMany<Tenant, $this>
     */
    public function subAccounts(): HasMany
    {
        return $this->hasMany(Tenant::class, 'firm_id');
    }

    public function subAccountCount(): int
    {
        return $this->subAccounts()->count();
    }

    /** Whether the plan cap still leaves room for another client workspace. */
    public function canAddSubAccount(): bool
    {
        return $this->subAccountCount() < $this->max_sub_accounts;
    }
}
