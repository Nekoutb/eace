<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Acf\AcfAuthenticityStatus;
use Carbon\CarbonImmutable;

/**
 * Central, cross-customer cache of ACF authenticity verdicts, keyed by the 620…
 * reference. Lives on the central database (CentralModel) so a verdict obtained
 * by one workspace is reused by every other — an ACF's authenticity is a global
 * fact, so the portal is driven once per reference, not once per customer.
 *
 * @property string $reference
 * @property ?string $niu
 * @property AcfAuthenticityStatus $authenticity_status
 * @property ?string $result_text
 * @property ?string $authenticity_url
 * @property ?string $snapshot_path
 * @property CarbonImmutable $checked_at
 */
class AcfAuthentication extends CentralModel
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authenticity_status' => AcfAuthenticityStatus::class,
            'checked_at' => 'immutable_datetime',
        ];
    }

    /** A cached verdict is only reusable when it is definitive (not a portal outage). */
    public function isDefinitive(): bool
    {
        return $this->authenticity_status !== AcfAuthenticityStatus::Unverified;
    }
}
