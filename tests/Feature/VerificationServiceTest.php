<?php

declare(strict_types=1);

use App\Domain\Niu\Verdict;
use App\Domain\Risk\PaymentMethod;
use App\Models\NiuRegistrySnapshot;
use App\Models\Tenant;
use App\Models\ThirdParty;
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

it('records a conforme verification for a clean active NIU', function () {
    $verification = $this->service->verify(new VerifyRequest(rawInput: 'M058712345678X'));

    expect($verification->verdict)->toBe(Verdict::Conforme)
        ->and($verification->niu)->toBe('M058712345678X')
        ->and($verification->raw_input)->toBe('M058712345678X')
        ->and($verification->exposure_xaf)->toBe(0)
        ->and($verification->snapshot_id)->not->toBeNull();
});

it('persists the vendor name from the register at verification time', function () {
    $verification = $this->service->verify(new VerifyRequest(rawInput: 'M058712345678X'));

    expect($verification->vendor_name)->toBe('SARL EXEMPLE');
});

it('leaves the vendor name null when the NIU is not in the register and no ACF or invoice names it', function () {
    $verification = $this->service->verify(new VerifyRequest(rawInput: 'M999999999999Z'));

    expect($verification->vendor_name)->toBeNull();
});

it('captures an immutable central snapshot on lookup', function () {
    $this->service->verify(new VerifyRequest(rawInput: 'M058712345678X'));

    $snapshot = NiuRegistrySnapshot::query()->where('niu', 'M058712345678X')->first();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->content_hash)->toHaveLength(64)
        ->and($snapshot->payload['fiscalis_status'])->toBe('actif');
});

it('records INTROUVABLE for a valid NIU absent from the file', function () {
    $verification = $this->service->verify(new VerifyRequest(rawInput: 'M999999999999Z'));

    expect($verification->verdict)->toBe(Verdict::Introuvable)
        ->and($verification->snapshot_id)->toBeNull();
});

it('records FORMAT_INVALIDE and keeps the raw input when the NIU is malformed', function () {
    $verification = $this->service->verify(new VerifyRequest(rawInput: 'pas-un-niu'));

    expect($verification->verdict)->toBe(Verdict::FormatInvalide)
        ->and($verification->niu)->toBeNull()
        ->and($verification->raw_input)->toBe('pas-un-niu');
});

it('records SUSPENDU and RADIE from the file status', function () {
    expect($this->service->verify(new VerifyRequest(rawInput: 'M111111111111A'))->verdict)
        ->toBe(Verdict::Suspendu)
        ->and($this->service->verify(new VerifyRequest(rawInput: 'M222222222222A'))->verdict)
        ->toBe(Verdict::Radie);
});

it('downgrades to réserves and prices exposure for a cash payment', function () {
    $verification = $this->service->verify(new VerifyRequest(
        rawInput: 'M058712345678X',
        invoiceAmountXaf: 250_000,
        paymentMethod: PaymentMethod::Cash,
    ));

    expect($verification->verdict)->toBe(Verdict::ConformeAvecReserves)
        ->and($verification->exposure_xaf)->toBeGreaterThan(0)
        ->and(collect($verification->triggered_rules)->pluck('code'))->toContain('R9');
});

it('updates the linked third party with the latest verdict', function () {
    $supplier = ThirdParty::create(['name' => 'SARL EXEMPLE', 'type' => 'supplier']);

    $this->service->verify(new VerifyRequest(
        rawInput: 'M222222222222A',
        thirdPartyId: $supplier->id,
    ));

    $supplier->refresh();

    expect($supplier->last_verdict)->toBe(Verdict::Radie)
        ->and($supplier->last_verified_at)->not->toBeNull();
});

it('persists findings as a structured array', function () {
    $verification = $this->service->verify(new VerifyRequest(rawInput: 'pas-un-niu'));

    expect($verification->triggered_rules)->toBeArray()
        ->and($verification->triggered_rules[0])->toHaveKeys(['code', 'article', 'message', 'exposure_xaf']);
});
