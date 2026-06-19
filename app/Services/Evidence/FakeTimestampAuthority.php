<?php

declare(strict_types=1);

namespace App\Services\Evidence;

use App\Domain\Evidence\TimestampStatus;
use Carbon\CarbonImmutable;

/**
 * A no-network stand-in used in tests and local development. It returns a
 * deterministic, clearly-marked fake token so the seal path can be exercised
 * without reaching a real Timestamp Authority. Never used in production (the
 * service provider binds the RFC-3161 authority once timestamping is enabled).
 */
final class FakeTimestampAuthority implements TimestampAuthority
{
    public function timestamp(string $sha256Hex): TimestampResult
    {
        return new TimestampResult(
            status: TimestampStatus::Signed,
            tokenDer: "FAKE-RFC3161-TOKEN\n".$sha256Hex,
            timestampedAt: CarbonImmutable::now(),
            tsaUrl: 'fake://timestamp-authority',
        );
    }
}
