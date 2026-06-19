<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Builds the human-readable band stamped onto every evidence screenshot, so the
 * image itself carries the moment and subject of capture — no longer a dateless
 * print-screen whose timing could be questioned. The cryptographic proof of that
 * timing is the separate RFC-3161 seal; this band is its readable counterpart.
 */
final class CaptureStamp
{
    /**
     * e.g. "Elite Advisors Compliance Engine — capture 2026-06-19 15:42:10
     * (UTC+01:00) — https://fanr.harmony2.cm/authentification?...".
     */
    public static function for(string $subject): string
    {
        $tz = (string) config('niu.timestamp.timezone', 'Africa/Douala');
        $when = CarbonImmutable::now()->setTimezone($tz)->format('Y-m-d H:i:s (P)');
        $brand = (string) config('app.name', 'Compliance Engine');

        return $brand.' — capture '.$when.' — '.self::sanitize($subject);
    }

    /**
     * The subject derives from OCR'd / user-supplied text (an ACF reference or a
     * NIU). Collapse it to a single line of printable ASCII so a crafted value
     * can't inject extra lines (e.g. a fake "AUTHENTIC: YES") into the band the
     * compliance reviewer reads off the screenshot.
     */
    private static function sanitize(string $subject): string
    {
        $clean = (string) preg_replace('/[^\x20-\x7E]+/', ' ', $subject);

        return mb_substr(trim($clean), 0, 300);
    }
}
