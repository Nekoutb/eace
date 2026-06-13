<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Niu\Verdict;
use App\Domain\ThirdParty\ThirdPartyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant-owned. A supplier or client in the tenant's directory. Uses the
 * default connection, which is the tenant connection while tenancy is
 * initialised — so it lives entirely in the tenant's own database.
 *
 * @property ?string $niu
 * @property string $name
 * @property ThirdPartyType $type
 * @property bool $is_strategic
 * @property ?Verdict $last_verdict
 */
class ThirdParty extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => ThirdPartyType::class,
            'last_verdict' => Verdict::class,
            'is_strategic' => 'boolean',
            'last_verified_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ThirdPartyDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ThirdPartyDocument::class);
    }

    /**
     * @return HasMany<Verification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }
}
