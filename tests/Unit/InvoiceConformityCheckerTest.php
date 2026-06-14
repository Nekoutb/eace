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
