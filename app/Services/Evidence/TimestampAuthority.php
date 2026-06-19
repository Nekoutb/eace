<?php

declare(strict_types=1);

namespace App\Services\Evidence;

/**
 * Seals a SHA-256 hash with an independent RFC-3161 trusted timestamp, proving
 * the hashed bytes existed at a specific instant vouched for by a third party.
 * Implementations never throw — a failure is reported through the result so a
 * capture is never blocked by a timestamp-authority outage.
 */
interface TimestampAuthority
{
    /** @param  string  $sha256Hex  the lowercase hex SHA-256 of the evidence bytes */
    public function timestamp(string $sha256Hex): TimestampResult;
}
