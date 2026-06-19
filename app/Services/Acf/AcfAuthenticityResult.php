<?php

declare(strict_types=1);

namespace App\Services\Acf;

use App\Domain\Acf\AcfAuthenticityStatus;
use Carbon\CarbonImmutable;

/**
 * Outcome of an ACF authenticity check for one reference. Immutable.
 * $screenshotPng holds the raw PNG bytes of the portal verdict (null when the
 * portal could not be reached / driven, or when the verdict was reused from the
 * shared cross-customer cache). $reusedAt is set when the verdict came from the
 * cache instead of a fresh portal call — carrying the date it was originally
 * obtained.
 */
final class AcfAuthenticityResult
{
    public function __construct(
        public readonly AcfAuthenticityStatus $status,
        public readonly string $url,
        public readonly ?string $resultText = null,
        public readonly ?string $screenshotPng = null,
        public readonly ?CarbonImmutable $reusedAt = null,
    ) {}

    /** True when this verdict was reused from the shared cache (no portal call). */
    public function wasReused(): bool
    {
        return $this->reusedAt !== null;
    }

    public static function unverified(string $url, ?string $note = null): self
    {
        return new self(AcfAuthenticityStatus::Unverified, $url, $note, null);
    }

    public function hasScreenshot(): bool
    {
        return $this->screenshotPng !== null && $this->screenshotPng !== '';
    }
}
