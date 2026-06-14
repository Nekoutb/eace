<?php

declare(strict_types=1);

use App\Domain\Approval\VerificationStage;
use App\Models\Tenant;
use App\Services\Verification\BatchVerificationService;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'acme']);
    tenancy()->initialize($this->tenant);
    $this->batch = app(BatchVerificationService::class);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('parses, trims, uppercases and de-duplicates free-text NIUs', function () {
    $parsed = $this->batch->parse("m058712345678x\nM111111111111A, m058712345678x\n  \nM222222222222A;P058712345678X");

    expect($parsed)->toBe([
        'M058712345678X',
        'M111111111111A',
        'M222222222222A',
        'P058712345678X',
    ]);
});

it('verifies a batch at the settlement stage', function () {
    $results = $this->batch->verifyBatch(['M058712345678X', 'M111111111111A']);

    expect($results)->toHaveCount(2)
        ->and($results->every(fn ($v) => $v->stage === VerificationStage::Settlement))->toBeTrue();
});

it('summarises accept/reject counts and exposure', function () {
    $results = $this->batch->verifyBatch(['M111111111111A', 'M222222222222A']);
    $summary = $this->batch->summarise($results);

    expect($summary['total'])->toBe(2)
        ->and($summary['rejected'])->toBe(2)
        ->and($summary['accepted'])->toBe(0)
        ->and($summary['exposure_xaf'])->toBeGreaterThanOrEqual(0);
});
