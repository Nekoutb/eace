<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfStatus;
use App\Domain\Acf\AcfValidity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-owned. A processed ACF under monitoring (CGI art. L 94 bis).
 * Dates are immutable so the validity computation can't accidentally mutate them.
 *
 * @property string $reference
 * @property ?string $niu
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable $expires_at
 * @property int $validity_months
 * @property bool $has_moratorium
 * @property AcfStatus $status
 * @property ?AcfAuthenticityStatus $authenticity_status
 * @property ?CarbonImmutable $last_verified_at
 */
class AcfCertificate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_date',
            'expires_at' => 'immutable_date',
            'last_verified_at' => 'immutable_datetime',
            'validity_months' => 'integer',
            'has_moratorium' => 'boolean',
            'status' => AcfStatus::class,
            'authenticity_status' => AcfAuthenticityStatus::class,
            'raw_payload' => 'array',
        ];
    }

    /** The configured "expiring soon" buffer, in days. */
    public static function bufferDays(): int
    {
        return (int) config('niu.acf_validity_buffer_days', AcfValidity::DEFAULT_BUFFER_DAYS);
    }

    /** Recompute expires_at and status from the current issue date / moratorium / authenticity. */
    public function recomputeValidity(?CarbonImmutable $now = null): static
    {
        $this->expires_at = AcfValidity::expiresAt($this->issued_at, $this->validity_months);
        $this->status = AcfValidity::status(
            $this->expires_at,
            $this->authenticity_status,
            $now ?? CarbonImmutable::now(),
            static::bufferDays(),
        );

        return $this;
    }

    /** Whether settlement may reuse this certificate rather than block payment. */
    public function isReusableForSettlement(): bool
    {
        return AcfValidity::isReusableForSettlement($this->status, $this->authenticity_status);
    }

    /**
     * @return BelongsTo<ThirdParty, $this>
     */
    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }
}
