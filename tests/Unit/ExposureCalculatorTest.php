<?php

declare(strict_types=1);

use App\Domain\Risk\ExposureCalculator;

beforeEach(function () {
    $this->calc = new ExposureCalculator;
});

it('computes VAT at 19.25%', function () {
    expect($this->calc->vatLoss(1_000_000))->toBe(192_500);
});

it('computes the IS impact of a rejected charge at 33%', function () {
    expect($this->calc->chargeLoss(1_000_000))->toBe(330_000);
});

it('treats null or non-positive amounts as zero exposure', function () {
    expect($this->calc->vatLoss(null))->toBe(0)
        ->and($this->calc->vatLoss(0))->toBe(0)
        ->and($this->calc->chargeLoss(-5))->toBe(0);
});

it('applies a 100% bad-faith majoration', function () {
    expect($this->calc->badFaithMajoration(192_500))->toBe(192_500);
});

it('returns the flat fraudulent-NIU fine', function () {
    expect($this->calc->niuFraudFine())->toBe(1_000_000);
});

it('floors the invoice fine at 100 000 F', function () {
    expect($this->calc->invoiceFine(50_000))->toBe(100_000)
        ->and($this->calc->invoiceFine(750_000))->toBe(750_000)
        ->and($this->calc->invoiceFine(null))->toBe(100_000);
});

it('detects the cash deductibility threshold', function () {
    expect($this->calc->cashThresholdBreached(100_000))->toBeTrue()
        ->and($this->calc->cashThresholdBreached(99_999))->toBeFalse()
        ->and($this->calc->cashThresholdBreached(null))->toBeFalse();
});

it('sums VAT + IS + majoration for a fully rejected invoice', function () {
    // 1 000 000 -> VAT 192 500 + IS 330 000 + majoration 192 500 = 715 000
    expect($this->calc->rejectedInvoiceExposure(1_000_000))->toBe(715_000);
});
