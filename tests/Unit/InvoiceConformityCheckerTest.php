<?php

declare(strict_types=1);

use App\Domain\Invoice\ConformityType;
use App\Domain\Invoice\ExemptionMention;
use App\Domain\Invoice\InvoiceConformityChecker;
use App\Domain\Invoice\InvoiceData;
use App\Domain\Niu\NiuNumber;
use Carbon\CarbonImmutable;

/** A fully-conform taxable invoice; override (incl. with null) per test. */
function invoice(array $o = []): InvoiceData
{
    $d = array_merge([
        'supplierNiu' => NiuNumber::fromString('M058712345678X'),
        'clientNiu' => NiuNumber::fromString('M020100021296Z'),
        'date' => CarbonImmutable::parse('2026-06-01'),
        'supplierName' => 'SARL EXEMPLE',
        'supplierAddress' => 'BP 1234, Douala',
        'supplierRccm' => 'RC/DLA/2020/B/123',
        'clientIdentity' => 'EXPRESS UNION FINANCE SA',
        'clientAddress' => 'BP 5000, Douala',
        'clientRccm' => 'RC/DLA/2015/B/777',
        'description' => 'Prestation de services informatiques',
        'htAmount' => 1_000_000,
        'vatRate' => 0.1925,
        'vatAmount' => 192_500,
        'ttcAmount' => 1_192_500,
        'exemption' => ExemptionMention::None,
    ], $o);

    return new InvoiceData(
        supplierNiu: $d['supplierNiu'],
        clientNiu: $d['clientNiu'],
        date: $d['date'],
        supplierName: $d['supplierName'],
        supplierAddress: $d['supplierAddress'],
        supplierRccm: $d['supplierRccm'],
        clientIdentity: $d['clientIdentity'],
        clientAddress: $d['clientAddress'],
        clientRccm: $d['clientRccm'],
        description: $d['description'],
        htAmount: $d['htAmount'],
        vatRate: $d['vatRate'],
        vatAmount: $d['vatAmount'],
        ttcAmount: $d['ttcAmount'],
        exemption: $d['exemption'],
    );
}

beforeEach(function () {
    $this->checker = new InvoiceConformityChecker;
});

it('passes a fully conform taxable invoice', function () {
    $result = $this->checker->check(invoice());

    expect($result->conform)->toBeTrue()
        ->and($result->failed())->toBeEmpty();
});

it('flags a missing mandatory mention (condition de forme)', function () {
    $result = $this->checker->check(invoice(['supplierRccm' => null]));

    expect($result->conform)->toBeFalse()
        ->and(collect($result->failed())->pluck('key'))->toContain('supplier_rccm');
});

it('flags an incoherent VAT amount (condition de fond)', function () {
    // 1,000,000 × 19.25% = 192,500, but the invoice states 200,000
    $result = $this->checker->check(invoice(['vatAmount' => 200_000, 'ttcAmount' => 1_200_000]));

    expect($result->conform)->toBeFalse()
        ->and(collect($result->failed())->pluck('key'))->toContain('vat_calc');
});

it('flags an incoherent TTC total', function () {
    $result = $this->checker->check(invoice(['ttcAmount' => 1_300_000]));

    expect($result->conform)->toBeFalse()
        ->and(collect($result->failed())->pluck('key'))->toContain('ttc_calc');
});

it('flags an illegal VAT rate', function () {
    $result = $this->checker->check(invoice(['vatRate' => 0.18, 'vatAmount' => 180_000, 'ttcAmount' => 1_180_000]));

    expect(collect($result->failed())->pluck('key'))->toContain('vat_rate');
});

it('accepts a properly exempt invoice (no VAT)', function () {
    $result = $this->checker->check(invoice([
        'exemption' => ExemptionMention::Exonere,
        'vatRate' => null,
        'vatAmount' => 0,
        'ttcAmount' => 1_000_000,
    ]));

    expect($result->conform)->toBeTrue();
});

it('checks the supplier NIU matches the verified one', function () {
    $expected = NiuNumber::fromString('M058712345678X');

    expect($this->checker->check(invoice(), $expected)->conform)->toBeTrue()
        ->and($this->checker->check(invoice(['supplierNiu' => NiuNumber::fromString('M999999999999Z')]), $expected)->conform)
        ->toBeFalse();
});

it('checks the client NIU and name match the organisation profile', function () {
    $clientNiu = NiuNumber::fromString('M020100021296Z');

    // Conform when both match (case-insensitive, substring-tolerant name).
    expect($this->checker->check(invoice(), null, $clientNiu, 'express union finance')->conform)->toBeTrue();

    // Wrong client NIU on the invoice → client_niu_match fails.
    $wrongNiu = $this->checker->check(invoice(['clientNiu' => NiuNumber::fromString('P111111111111A')]), null, $clientNiu, null);
    expect($wrongNiu->conform)->toBeFalse()
        ->and(collect($wrongNiu->failed())->pluck('key'))->toContain('client_niu_match');

    // Wrong client name on the invoice → client_identity_match fails.
    $wrongName = $this->checker->check(invoice(['clientIdentity' => 'AUTRE CLIENT SARL']), null, null, 'EXPRESS UNION FINANCE SA');
    expect($wrongName->conform)->toBeFalse()
        ->and(collect($wrongName->failed())->pluck('key'))->toContain('client_identity_match');
});

it('accepts the client identity when it matches an accepted name variation', function () {
    // The invoice names the buyer "EU FINANCE" — neither contains nor is contained
    // by the primary name "EXPRESS UNION FINANCE SA", so the plain check fails.
    $invoice = invoice(['clientIdentity' => 'EU FINANCE']);

    $withoutAlias = $this->checker->check($invoice, null, null, 'EXPRESS UNION FINANCE SA');
    expect(collect($withoutAlias->failed())->pluck('key'))->toContain('client_identity_match');

    // Registering "EU FINANCE" as an accepted variation makes it pass.
    $withAlias = $this->checker->check(
        $invoice, null, null, 'EXPRESS UNION FINANCE SA', null, null,
        ['EXPRESS UNION FINANCE SA', 'EU FINANCE'],
    );
    expect(collect($withAlias->failed())->pluck('key'))->not->toContain('client_identity_match');
});

it('still enforces the client identity from variations when the primary name is blank', function () {
    // The buyer left the primary entity name empty but registered a variation.
    $passes = $this->checker->check(
        invoice(['clientIdentity' => 'EU FINANCE']), null, null, null, null, null, ['EU FINANCE'],
    );
    $hit = collect($passes->findings)->firstWhere('key', 'client_identity_match');
    expect($hit)->not->toBeNull()
        ->and($hit->passed)->toBeTrue();

    // And the same control still FAILS for a buyer name that matches no variation.
    $fails = $this->checker->check(
        invoice(['clientIdentity' => 'AUTRE CLIENT SARL']), null, null, null, null, null, ['EU FINANCE'],
    );
    expect(collect($fails->failed())->pluck('key'))->toContain('client_identity_match');
});

it('checks the client RCCM and address match the organisation profile', function () {
    // Conform when both match (punctuation/whitespace tolerant).
    expect($this->checker->check(invoice(), null, null, null, 'RC/DLA/2015/B/777', 'BP 5000 Douala')->conform)->toBeTrue();

    // Wrong client RCCM on the invoice.
    $badRccm = $this->checker->check(invoice(['clientRccm' => 'RC/XXX/0000/Z/0']), null, null, null, 'RC/DLA/2015/B/777', null);
    expect(collect($badRccm->failed())->pluck('key'))->toContain('client_rccm_match');

    // Wrong client address on the invoice.
    $badAddr = $this->checker->check(invoice(['clientAddress' => 'Autre adresse, Yaoundé']), null, null, null, null, 'BP 5000 Douala');
    expect(collect($badAddr->failed())->pluck('key'))->toContain('client_address_match');
});

it('narrates how the control was performed and the origin of the variance', function () {
    // 1,000,000 × 19.25% = 192,500, but the invoice states 200,000 → variance 7,500.
    $result = $this->checker->check(invoice(['vatAmount' => 200_000, 'ttcAmount' => 1_200_000]));
    $vat = collect($result->findings)->firstWhere('key', 'vat_calc');

    expect($vat->detail)->toContain('192 500')   // expected VAT
        ->and($vat->detail)->toContain('200 000') // what the invoice states
        ->and($vat->detail)->toContain('7 500');  // the variance

    // Forme checks narrate presence/absence of the mandatory mention.
    $missing = collect($this->checker->check(invoice(['supplierRccm' => null]))->findings)->firstWhere('key', 'supplier_rccm');
    expect($missing->detail)->not->toBeNull()
        ->and($missing->passed)->toBeFalse();
});

it('separates forme and fond findings', function () {
    $result = $this->checker->check(invoice());

    expect($result->ofType(ConformityType::Forme))->not->toBeEmpty()
        ->and($result->ofType(ConformityType::Fond))->not->toBeEmpty();
});

it('tolerates small rounding differences', function () {
    // VAT off by 1 FCFA, TTC adjusted — within tolerance
    $result = $this->checker->check(invoice(['vatAmount' => 192_499, 'ttcAmount' => 1_192_499]));

    expect($result->conform)->toBeTrue();
});
