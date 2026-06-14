<?php

declare(strict_types=1);

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Models\AcfCertificate;
use App\Models\Tenant;
use App\Services\Verification\VerificationService;
use App\Services\Verification\VerifyRequest;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'acme']);
    tenancy()->initialize($this->tenant);
    $this->service = app(VerificationService::class);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** A clean, authentic, in-force ACF for the demo active NIU. */
function authenticAcf(string $niu = 'M058712345678X'): AcfCertificate
{
    $acf = new AcfCertificate([
        'reference' => '999000111222',
        'niu' => $niu,
        'taxpayer_name' => 'SARL EXEMPLE',
        'issued_at' => now(),
        'validity_months' => 3,
        'authenticity_status' => AcfAuthenticityStatus::Authentic,
    ]);
    $acf->recomputeValidity()->save();

    return $acf;
}

it('accepts when all four checks pass', function () {
    authenticAcf();

    $verification = $this->service->verify(new VerifyRequest(
        rawInput: 'M058712345678X',
        stage: VerificationStage::Recording,
    ));

    expect($verification->approval)->toBe(ApprovalDecision::Accepted)
        ->and($verification->stage)->toBe(VerificationStage::Recording)
        ->and($verification->checks)->toHaveCount(4)
        ->and(collect($verification->checks)->every(fn ($c) => $c['passed']))->toBeTrue();
});

it('rejects when no ACF backs the NIU (authenticity + validity fail)', function () {
    $verification = $this->service->verify(new VerifyRequest(rawInput: 'M058712345678X'));

    expect($verification->approval)->toBe(ApprovalDecision::Rejected)
        ->and(collect($verification->checks)->where('passed', false))->not->toBeEmpty();
});

it('rejects a struck-off NIU even with an authentic ACF', function () {
    authenticAcf('M222222222222A'); // M222… is "Radié" in the fake resolver

    $verification = $this->service->verify(new VerifyRequest(rawInput: 'M222222222222A'));

    expect($verification->approval)->toBe(ApprovalDecision::Rejected);
});

it('records the settlement stage', function () {
    authenticAcf();

    $verification = $this->service->verify(new VerifyRequest(
        rawInput: 'M058712345678X',
        stage: VerificationStage::Settlement,
    ));

    expect($verification->stage)->toBe(VerificationStage::Settlement);
});

it('links the resolved ACF to the verification', function () {
    $acf = authenticAcf();

    $verification = $this->service->verify(new VerifyRequest(
        rawInput: 'M058712345678X',
        acfReference: '999000111222',
    ));

    expect($verification->acf_certificate_id)->toBe($acf->id);
});
