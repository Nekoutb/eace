<?php

declare(strict_types=1);

namespace App\Domain\Acf;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Pure ACF validity logic (CGI art. L 94 bis). Kept framework-light and free of
 * Eloquent so it is unit-testable with fixed dates. The model and the ingestion
 * service delegate here.
 */
final class AcfValidity
{
    public const DEFAULT_BUFFER_DAYS = 5;

    public const DEFAULT_VALIDITY_MONTHS = 3;

    public const MORATORIUM_VALIDITY_MONTHS = 1;

    /**
     * Expiry = issue date + the validity window, clamped so e.g. 31 Jan + 1 month
     * lands on 28/29 Feb rather than spilling into March.
     */
    public static function expiresAt(CarbonInterface $issuedAt, int $validityMonths): CarbonImmutable
    {
        return CarbonImmutable::instance($issuedAt)->startOfDay()->addMonthsNoOverflow($validityMonths);
    }

    /**
     * The validity window in months: the value printed on the ACF if known,
     * otherwise 1 month under a moratorium, else the standard 3.
     */
    public static function validityMonths(?int $printedMonths, bool $hasMoratorium): int
    {
        if ($printedMonths !== null && $printedMonths > 0) {
            return $printedMonths;
        }

        return $hasMoratorium ? self::MORATORIUM_VALIDITY_MONTHS : self::DEFAULT_VALIDITY_MONTHS;
    }

    /**
     * Lifecycle status on a date granularity: a non-authentic certificate is
     * worthless regardless of dates; otherwise valid through the expiry day,
     * expiring-soon once inside the buffer window, expired past it.
     */
    public static function status(
        CarbonInterface $expiresAt,
        ?AcfAuthenticityStatus $authenticity,
        CarbonInterface $now,
        int $bufferDays = self::DEFAULT_BUFFER_DAYS,
    ): AcfStatus {
        if ($authenticity === AcfAuthenticityStatus::NotAuthentic) {
            return AcfStatus::NotAuthentic;
        }

        $expiry = CarbonImmutable::instance($expiresAt)->startOfDay();
        $today = CarbonImmutable::instance($now)->startOfDay();

        if ($today->greaterThan($expiry)) {
            return AcfStatus::Expired;
        }

        if ($today->greaterThanOrEqualTo($expiry->subDays($bufferDays))) {
            return AcfStatus::ExpiringSoon;
        }

        return AcfStatus::Valid;
    }

    /**
     * Settlement may reuse a recording-time ACF only if it is authentic AND
     * cleanly valid (with the full buffer remaining). Otherwise payment blocks.
     */
    public static function isReusableForSettlement(AcfStatus $status, ?AcfAuthenticityStatus $authenticity): bool
    {
        return $authenticity === AcfAuthenticityStatus::Authentic && $status === AcfStatus::Valid;
    }

    /**
     * Whole days from `now` until expiry on a date granularity: positive in the
     * future, 0 on the expiry day itself, negative once past. Drives the
     * Certificates-screen reminder dot and countdown text.
     */
    public static function daysToExpiry(CarbonInterface $expiresAt, CarbonInterface $now): int
    {
        $expiry = CarbonImmutable::instance($expiresAt)->startOfDay();
        $today = CarbonImmutable::instance($now)->startOfDay();

        return (int) $today->diffInDays($expiry, false);
    }

    /**
     * Reminder-dot level from days-to-expiry: expired once negative, red inside
     * the red window, orange inside the (wider) orange window, none beyond it.
     * A purely visual reminder cue — independent of the settlement buffer.
     */
    public static function expiryAlert(int $daysToExpiry, int $orangeDays, int $redDays): AcfExpiryAlert
    {
        if ($daysToExpiry < 0) {
            return AcfExpiryAlert::Expired;
        }

        if ($daysToExpiry <= $redDays) {
            return AcfExpiryAlert::Red;
        }

        if ($daysToExpiry <= $orangeDays) {
            return AcfExpiryAlert::Orange;
        }

        return AcfExpiryAlert::None;
    }
}
