<?php

declare(strict_types=1);

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfExpiryAlert;
use App\Domain\Acf\AcfStatus;
use App\Domain\Acf\AcfValidity;
use Carbon\CarbonImmutable;

describe('expiry', function () {
    it('adds 3 months for a standard ACF', function () {
        expect(AcfValidity::expiresAt(CarbonImmutable::parse('2026-03-19'), 3)->toDateString())
            ->toBe('2026-06-19');
    });

    it('adds 1 month for a moratorium ACF', function () {
        expect(AcfValidity::expiresAt(CarbonImmutable::parse('2026-03-19'), 1)->toDateString())
            ->toBe('2026-04-19');
    });

    it('clamps month overflow (31 Jan + 1 month → 28 Feb)', function () {
        expect(AcfValidity::expiresAt(CarbonImmutable::parse('2026-01-31'), 1)->toDateString())
            ->toBe('2026-02-28');
    });
});

describe('validity months', function () {
    it('prefers the printed months', function () {
        expect(AcfValidity::validityMonths(3, false))->toBe(3);
    });

    it('falls back to 1 month under a moratorium when not printed', function () {
        expect(AcfValidity::validityMonths(null, true))->toBe(1);
    });

    it('falls back to the standard 3 months otherwise', function () {
        expect(AcfValidity::validityMonths(null, false))->toBe(3);
    });
});

describe('status', function () {
    $expires = CarbonImmutable::parse('2026-06-19');
    $auth = AcfAuthenticityStatus::Authentic;

    it('is valid well before expiry', function () use ($expires, $auth) {
        expect(AcfValidity::status($expires, $auth, CarbonImmutable::parse('2026-06-01'), 5))
            ->toBe(AcfStatus::Valid);
    });

    it('is still valid on the expiry day itself', function () use ($expires, $auth) {
        expect(AcfValidity::status($expires, $auth, CarbonImmutable::parse('2026-06-19'), 5))
            ->toBe(AcfStatus::ExpiringSoon); // within the 5-day buffer, but not expired
    });

    it('is expired the day after expiry', function () use ($expires, $auth) {
        expect(AcfValidity::status($expires, $auth, CarbonImmutable::parse('2026-06-20'), 5))
            ->toBe(AcfStatus::Expired);
    });

    it('is expiring-soon exactly on the buffer cutoff', function () use ($expires, $auth) {
        // 5 days before 19 June = 14 June
        expect(AcfValidity::status($expires, $auth, CarbonImmutable::parse('2026-06-14'), 5))
            ->toBe(AcfStatus::ExpiringSoon);
    });

    it('is still valid the day before the buffer cutoff', function () use ($expires, $auth) {
        expect(AcfValidity::status($expires, $auth, CarbonImmutable::parse('2026-06-13'), 5))
            ->toBe(AcfStatus::Valid);
    });

    it('is not-authentic regardless of dates when Harmony2 fails', function () use ($expires) {
        expect(AcfValidity::status($expires, AcfAuthenticityStatus::NotAuthentic, CarbonImmutable::parse('2026-06-01'), 5))
            ->toBe(AcfStatus::NotAuthentic);
    });
});

describe('settlement reuse', function () {
    it('allows reuse only when authentic and cleanly valid', function () {
        expect(AcfValidity::isReusableForSettlement(AcfStatus::Valid, AcfAuthenticityStatus::Authentic))->toBeTrue();
    });

    it('blocks reuse when expiring soon', function () {
        expect(AcfValidity::isReusableForSettlement(AcfStatus::ExpiringSoon, AcfAuthenticityStatus::Authentic))->toBeFalse();
    });

    it('blocks reuse when authenticity is unverified', function () {
        expect(AcfValidity::isReusableForSettlement(AcfStatus::Valid, AcfAuthenticityStatus::Unverified))->toBeFalse();
    });
});

describe('expiry countdown + reminder alert', function () {
    $expires = CarbonImmutable::parse('2026-06-30');

    it('counts whole days to expiry, signed (future positive, past negative)', function () use ($expires) {
        expect(AcfValidity::daysToExpiry($expires, CarbonImmutable::parse('2026-06-20')))->toBe(10)
            ->and(AcfValidity::daysToExpiry($expires, CarbonImmutable::parse('2026-06-30')))->toBe(0)
            ->and(AcfValidity::daysToExpiry($expires, CarbonImmutable::parse('2026-07-03')))->toBe(-3);
    });

    it('maps days-to-expiry to the dot level at the 20/10 thresholds', function () {
        $alert = fn (int $days): AcfExpiryAlert => AcfValidity::expiryAlert($days, 20, 10);

        expect($alert(25))->toBe(AcfExpiryAlert::None)      // plenty of time
            ->and($alert(21))->toBe(AcfExpiryAlert::None)
            ->and($alert(20))->toBe(AcfExpiryAlert::Orange) // hits orange at 20
            ->and($alert(11))->toBe(AcfExpiryAlert::Orange)
            ->and($alert(10))->toBe(AcfExpiryAlert::Red)     // hits red at 10
            ->and($alert(1))->toBe(AcfExpiryAlert::Red)
            ->and($alert(0))->toBe(AcfExpiryAlert::Red)      // expiry day
            ->and($alert(-1))->toBe(AcfExpiryAlert::Expired);
    });
});
