<?php

declare(strict_types=1);

namespace App\Services\Fiscalis;

use App\Domain\Fiscalis\FiscalisPresence;

/**
 * Outcome of a live DGI national-file consultation for one NIU. Immutable.
 * $screenshotPng holds the raw PNG bytes of the portal result (null when the
 * portal could not be reached / driven).
 */
final class FiscalisResult
{
    public function __construct(
        public readonly FiscalisPresence $presence,
        public readonly string $url,
        public readonly int $count = 0,
        public readonly ?string $resultText = null,
        public readonly ?string $screenshotPng = null,
    ) {}

    public static function unverified(string $url, ?string $note = null): self
    {
        return new self(FiscalisPresence::Unverified, $url, 0, $note, null);
    }

    public function hasScreenshot(): bool
    {
        return $this->screenshotPng !== null && $this->screenshotPng !== '';
    }
}
