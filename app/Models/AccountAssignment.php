<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\UserRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Central. One firm member's access to one client sub-account, with the role they
 * hold inside that workspace (admin / recorder / settler). The source of truth for
 * the member's workspace switcher — they see ONLY the accounts listed here.
 *
 * @property int $id
 * @property int $firm_member_id
 * @property string $tenant_id
 * @property UserRole $account_role
 * @property ?int $assigned_by
 */
class AccountAssignment extends CentralModel
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_role' => UserRole::class,
        ];
    }

    /**
     * @return BelongsTo<FirmMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(FirmMember::class, 'firm_member_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
