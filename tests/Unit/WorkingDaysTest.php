<?php

declare(strict_types=1);

use App\Support\WorkingDays;
use Carbon\CarbonImmutable;

it('counts only weekdays between two dates', function () {
    // Fri 3 Jul 2026 → Fri 10 Jul 2026 = Mon,Tue,Wed,Thu,Fri = 5 working days
    // (the intervening weekend Sat 4 / Sun 5 is not counted).
    $from = CarbonImmutable::parse('2026-07-03'); // Friday
    $to = CarbonImmutable::parse('2026-07-10');   // Friday

    expect(WorkingDays::between($from, $to))->toBe(5);
});

it('returns 0 for the same day or a backwards range', function () {
    $day = CarbonImmutable::parse('2026-07-08');

    expect(WorkingDays::between($day, $day))->toBe(0)
        ->and(WorkingDays::between($day, $day->subDays(3)))->toBe(0);
});

it('ignores the weekend when only a Saturday/Sunday have passed', function () {
    // Fri → Mon spans only Sat+Sun+Mon → 1 working day (the Monday).
    $from = CarbonImmutable::parse('2026-07-03'); // Friday
    $to = CarbonImmutable::parse('2026-07-06');   // Monday

    expect(WorkingDays::between($from, $to))->toBe(1);
});

it('treats a user with no last sign-in as inactive', function () {
    expect(WorkingDays::isInactive(null, 5))->toBeTrue();
});

it('flags inactivity strictly beyond the working-day threshold', function () {
    $now = CarbonImmutable::parse('2026-07-13'); // Monday

    // 5 working days ago (Mon 6 Jul) → exactly the threshold → NOT yet inactive.
    expect(WorkingDays::isInactive(CarbonImmutable::parse('2026-07-06'), 5, $now))->toBeFalse();

    // 6+ working days ago (Fri 3 Jul) → beyond the threshold → inactive.
    expect(WorkingDays::isInactive(CarbonImmutable::parse('2026-07-03'), 5, $now))->toBeTrue();

    // Signed in today → active.
    expect(WorkingDays::isInactive($now, 5, $now))->toBeFalse();
});
