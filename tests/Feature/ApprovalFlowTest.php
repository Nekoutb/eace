<?php

declare(strict_types=1);

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Domain\Invoice\InvoiceData;
use App\Domain\Niu\NiuNumber;
use App\Models\AcfCertificate;
use App\Models\Tenant;
use App\Services\Verification\VerificationService;
use App\Services\Verification\VerifyRequest;
use Carbon\CarbonImmutable;

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

/** A fully art.-150-conform invoice for the demo supplier. */
function conformInvoice(): InvoiceData
{
    return new InvoiceData(
        supplierNiu: NiuNumber::fromString('M058712345678X'),
        clientNiu: NiuNumber::fromString('M020100021296Z'),
        date: CarbonImmutable::parse('2026-06-01'),
        supplierName: 'SARL EXEMPLE',
        supplierAddress: 'BP 1234, Douala',
        supplierRccm: 'RC/DLA/2020/B/123',
        clientIdentity: 'EXPRESS UNION FINANCE SA',
        description: 'Prestation de services',
        htAmount: 1_000_000,
        vatRate: 0.1925,
        vatAmount: 192_500,
        ttcAmount: 1_192_500,
    );
}

it('keeps the recording accepted when the submitted invoice is conform', function () {
    authenticAcf();

    $verification = $this->service->verify(new VerifyRequest(
        rawInput: 'M058712345678X',
        invoiceData: conformInvoice(),
    ));

    expect($verification->approval)->toBe(ApprovalDecision::Accepted)
        ->and($verification->invoice_checks)->not->toBeNull()
        ->and($verification->invoice_data)->not->toBeNull();
});

it('rejects the recording when the submitted invoice fails forme/fond', function () {
    authenticAcf();

    // missing mandatory mentions + incoherent totals
    $bad = new InvoiceData(
        supplierNiu: NiuNumber::fromString('M058712345678X'),
        htAmount: 1_000_000,
        vatRate: 0.1925,
        vatAmount: 999,
        ttcAmount: 1,
    );

    $verification = $this->service->verify(new VerifyRequest(
        rawInput: 'M058712345678X',
        invoiceData: $bad,
    ));

    expect($verification->approval)->toBe(ApprovalDecision::Rejected);
});

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
