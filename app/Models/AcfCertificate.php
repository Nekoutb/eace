<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfExpiryAlert;
use App\Domain\Acf\AcfStatus;
use App\Domain\Acf\AcfValidity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property ?string $authenticity_url
 * @property ?string $authenticity_snapshot_path
 * @property ?string $authenticity_note
 * @property ?CarbonImmutable $last_verified_at
 * @property ?CarbonImmutable $reminded_at
 * @property int $reminder_count
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
            'reminded_at' => 'immutable_datetime',
            'reminder_count' => 'integer',
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

    /** Whole days until expiry: positive in the future, 0 today, negative once past. */
    public function daysToExpiry(?CarbonImmutable $now = null): int
    {
        return AcfValidity::daysToExpiry($this->expires_at, $now ?? CarbonImmutable::now());
    }

    /** The reminder-dot level (none/orange/red/expired) from the configured thresholds. */
    public function expiryAlert(?CarbonImmutable $now = null): AcfExpiryAlert
    {
        return AcfValidity::expiryAlert(
            $this->daysToExpiry($now),
            (int) config('niu.acf_reminder.orange_days', 20),
            (int) config('niu.acf_reminder.red_days', 10),
        );
    }

    /**
     * @return BelongsTo<ThirdParty, $this>
     */
    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    /**
     * Every invoice verification recorded against this certificate — one ACF
     * commonly covers several of a vendor's invoices (each its own verification).
     *
     * @return HasMany<Verification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }
}
