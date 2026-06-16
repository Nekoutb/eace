<?php

declare(strict_types=1);

namespace App\Services\Acf;

/**
 * Confirms an ACF's authenticity against the DGI Harmony2 portal
 * (CGI art. L 94 bis al. 3 — the QR/reference → published-list check).
 *
 * Implementations must never throw: an unreachable or undriveable portal
 * returns an "unverified" result so the verification degrades gracefully.
 */
interface AcfAuthenticityConnector
{
    public function verify(string $reference): AcfAuthenticityResult;
}
