<?php

declare(strict_types=1);

namespace App\Services\Acf;

use App\Domain\Acf\AcfAuthenticityStatus;

/**
 * Outcome of a live Harmony2 authenticity check for one ACF reference.
 * Immutable. $screenshotPng holds the raw PNG bytes of the portal verdict
 * (null when the portal could not be reached / driven).
 */
final class AcfAuthenticityResult
{
    public function __construct(
        public readonly AcfAuthenticityStatus $status,
        public readonly string $url,
        public readonly ?string $resultText = null,
        public readonly ?string $screenshotPng = null,
    ) {}

    public static function unverified(string $url, ?string $note = null): self
    {
        return new self(AcfAuthenticityStatus::Unverified, $url, $note, null);
    }

    public function hasScreenshot(): bool
    {
        return $this->screenshotPng !== null && $this->screenshotPng !== '';
    }
}
