<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Working-days (Monday–Friday) arithmetic — used to decide when a customer has
 * gone quiet. Weekends are excluded; public holidays are NOT modelled (Cameroon
 * has no holiday calendar in the app), so this is a weekday count, deliberately
 * simple. "Inactivity" is measured from a user's last sign-in.
 */
final class WorkingDays
{
    /**
     * Whole working days elapsed FROM `$from` UP TO `$to` — the count of
     * Mon–Fri days strictly after `$from`'s day, through `$to`'s day. Same day,
     * or `$to` before `$from`, yields 0.
     */
    public static function between(CarbonInterface $from, CarbonInterface $to): int
    {
        $cursor = CarbonImmutable::instance($from)->startOfDay();
        $end = CarbonImmutable::instance($to)->startOfDay();

        if ($end->lessThanOrEqualTo($cursor)) {
            return 0;
        }

        $days = 0;
        while ($cursor->lessThan($end)) {
            $cursor = $cursor->addDay();
            if ($cursor->isWeekday()) {
                $days++;
            }
        }

        return $days;
    }

    /**
     * Has this user gone quiet? True when they have NEVER signed in, or when
     * more than `$threshold` working days have passed since their last sign-in.
     */
    public static function isInactive(?CarbonInterface $lastLogin, int $threshold, ?CarbonInterface $now = null): bool
    {
        if ($lastLogin === null) {
            return true;
        }

        return self::between($lastLogin, $now ?? CarbonImmutable::now()) > $threshold;
    }
}
