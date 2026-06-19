<?php

declare(strict_types=1);

namespace App\Services\Evidence;

use App\Domain\Evidence\TimestampStatus;
use Carbon\CarbonImmutable;

/**
 * The outcome of asking a Timestamp Authority to seal one SHA-256 hash: the
 * status, the signed RFC-3161 token bytes (when signed), the authority-asserted
 * instant, and the authority URL — plus an error note for a failed attempt.
 */
final readonly class TimestampResult
{
    public function __construct(
        public TimestampStatus $status,
        public ?string $tokenDer = null,
        public ?CarbonImmutable $timestampedAt = null,
        public ?string $tsaUrl = null,
        public ?string $error = null,
    ) {}

    public static function disabled(): self
    {
        return new self(TimestampStatus::Disabled);
    }

    public static function failed(string $tsaUrl, string $error): self
    {
        return new self(TimestampStatus::Failed, tsaUrl: $tsaUrl, error: $error);
    }

    public static function pending(string $tsaUrl, string $error): self
    {
        return new self(TimestampStatus::Pending, tsaUrl: $tsaUrl, error: $error);
    }

    public function isSigned(): bool
    {
        return $this->status === TimestampStatus::Signed && $this->tokenDer !== null;
    }
}
