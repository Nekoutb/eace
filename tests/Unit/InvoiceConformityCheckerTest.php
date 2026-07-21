<?php

declare(strict_types=1);

use App\Domain\Acf\VendorRegime;
use App\Domain\Invoice\ConformityFinding;
use App\Domain\Invoice\ConformityType;
use App\Domain\Invoice\ExemptionMention;
use App\Domain\Invoice\InvoiceConformityChecker;
use App\Domain\Invoice\InvoiceConformityResult;
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
    // A taxable (VAT-carrying) invoice is only fully conform from a Réel vendor.
    $result = $this->checker->check(invoice(), vendorRegime: 'RÉEL');

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

    expect($this->checker->check(invoice(), $expected, vendorRegime: 'RÉEL')->conform)->toBeTrue()
        ->and($this->checker->check(invoice(['supplierNiu' => NiuNumber::fromString('M999999999999Z')]), $expected, vendorRegime: 'RÉEL')->conform)
        ->toBeFalse();
});

it('checks the client NIU and name match the organisation profile', function () {
    $clientNiu = NiuNumber::fromString('M020100021296Z');

    // Conform when both match (case-insensitive, substring-tolerant name).
    expect($this->checker->check(invoice(), null, $clientNiu, 'express union finance', vendorRegime: 'RÉEL')->conform)->toBeTrue();

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
        $invoice, null, null, 'EXPRESS UNION FINANCE SA', null,
        ['EXPRESS UNION FINANCE SA', 'EU FINANCE'],
    );
    expect(collect($withAlias->failed())->pluck('key'))->not->toContain('client_identity_match');
});

it('still enforces the client identity from variations when the primary name is blank', function () {
    // The buyer left the primary entity name empty but registered a variation.
    $passes = $this->checker->check(
        invoice(['clientIdentity' => 'EU FINANCE']), null, null, null, null, ['EU FINANCE'],
    );
    $hit = collect($passes->findings)->firstWhere('key', 'client_identity_match');
    expect($hit)->not->toBeNull()
        ->and($hit->passed)->toBeTrue();

    // And the same control still FAILS for a buyer name that matches no variation.
    $fails = $this->checker->check(
        invoice(['clientIdentity' => 'AUTRE CLIENT SARL']), null, null, null, null, ['EU FINANCE'],
    );
    expect(collect($fails->failed())->pluck('key'))->toContain('client_identity_match');
});

it('never judges the customer RCCM — not a legal mention (CGI art. 150)', function () {
    // An invoice WITHOUT any client RCCM is fully conform; there is no
    // client_rccm_match control at all any more.
    $noClientRccm = $this->checker->check(invoice(['clientRccm' => null]), vendorRegime: 'RÉEL');

    expect($noClientRccm->conform)->toBeTrue()
        ->and(collect($noClientRccm->findings)->pluck('key'))->not->toContain('client_rccm_match');
});

it('checks the client address match when the profile provides one', function () {
    // Conform when it matches (punctuation/whitespace tolerant).
    expect($this->checker->check(invoice(), null, null, null, 'BP 5000 Douala', vendorRegime: 'RÉEL')->conform)->toBeTrue();

    // Wrong client address on the invoice.
    $badAddr = $this->checker->check(invoice(['clientAddress' => 'Autre adresse, Yaoundé']), null, null, null, 'BP 5000 Douala');
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
    $result = $this->checker->check(invoice(['vatAmount' => 192_499, 'ttcAmount' => 1_192_499]), vendorRegime: 'RÉEL');

    expect($result->conform)->toBeTrue();
});

/*
 * VAT is governed by the vendor's régime. Only a Régime du Réel vendor may
 * invoice VAT — for them the figure checks run as usual. A vendor whose régime
 * forbids VAT (Simplifié, IGS, non-business) must carry none: the figure checks
 * do not apply, and any VAT present is rejected as "included in error" — a
 * régime finding (ConformityType::Regime), NOT an art. 150 forme/fond defect.
 * An undetermined régime is judged conservatively: figures verified, never
 * rejected merely for being unknown.
 */

function regimeFinding(InvoiceConformityResult $result): ?ConformityFinding
{
    foreach ($result->findings as $finding) {
        if ($finding->key === 'vat_not_allowed') {
            return $finding;
        }
    }

    return null;
}

it('lets a Régime du Réel vendor invoice VAT — no régime finding, fully conform', function () {
    $result = $this->checker->check(invoice(), vendorRegime: 'RÉEL');

    expect($result->conform)->toBeTrue()
        ->and(regimeFinding($result))->toBeNull(); // VAT is legitimate; nothing to flag
});

it('rejects VAT billed by a non-Réel vendor as included in error, not an art. 150 defect', function (string $label) {
    $result = $this->checker->check(invoice(), vendorRegime: $label);

    $finding = regimeFinding($result);
    expect($finding)->not->toBeNull()
        ->and($finding->passed)->toBeFalse()
        ->and($finding->type)->toBe(ConformityType::Regime) // NOT Forme/Fond (art. 150)
        ->and($result->conform)->toBeFalse()
        // The art. 150 VAT-figure checks are not applied to this vendor at all.
        ->and(collect($result->findings)->pluck('key'))
        ->not->toContain('vat')
        ->not->toContain('vat_calc')
        ->not->toContain('vat_rate');
})->with([
    'SIMPLIFIE', 'Régime Simplifié', 'IGS', 'Impôt Général Synthétique',
    'ORGANISME À BUT NON LUCRATIF', 'NON PROFESSIONNEL', "HORS REGIME D'IMPOSITION",
]);

it('accepts a non-Réel vendor who correctly bills no VAT', function (string $label) {
    // HT only, no VAT, TTC = HT. Previously the art. 150 VAT-mention check
    // rejected this; now it is simply correct for the vendor's régime.
    $noVat = invoice(['vatRate' => null, 'vatAmount' => null, 'ttcAmount' => 1_000_000]);

    $result = $this->checker->check($noVat, vendorRegime: $label);

    $finding = regimeFinding($result);
    expect($result->conform)->toBeTrue()
        ->and($finding)->not->toBeNull()
        ->and($finding->passed)->toBeTrue();
})->with(['IGS', 'SIMPLIFIE', 'ORGANISME À BUT NON LUCRATIF', 'NON PROFESSIONNEL']);

it('verifies the VAT figures (not the régime) when the régime is undetermined', function () {
    // Valid VAT + unknown régime → the figure checks run and pass; there is no
    // régime rejection just because the régime could not be established.
    $result = $this->checker->check(invoice(), vendorRegime: null);

    expect($result->conform)->toBeTrue()
        ->and(regimeFinding($result))->toBeNull();

    // A wrong VAT figure under an undetermined régime still fails on the figure.
    $bad = $this->checker->check(invoice(['vatAmount' => 200_000, 'ttcAmount' => 1_200_000]), vendorRegime: null);
    expect(collect($bad->failed())->pluck('key'))->toContain('vat_calc');
});

it('still fails a wrong VAT rate for a Régime du Réel vendor', function () {
    $result = $this->checker->check(
        invoice(['vatRate' => 0.18, 'vatAmount' => 180_000, 'ttcAmount' => 1_180_000]),
        vendorRegime: 'RÉEL',
    );

    expect(collect($result->failed())->pluck('key'))->toContain('vat_rate');
});

it('normalises the régime spelling variants found on documents', function () {
    $r = fn (?string $l) => VendorRegime::fromLabel($l);

    expect($r('Régime du réel'))->toBe(VendorRegime::Reel)
        ->and($r('REEL'))->toBe(VendorRegime::Reel)
        ->and($r('R.SIMPLIFIE'))->toBe(VendorRegime::Simplifie)
        ->and($r('Régime simplifié'))->toBe(VendorRegime::Simplifie)
        ->and($r('IGS'))->toBe(VendorRegime::Igs)
        ->and($r('Impôt Général Synthétique'))->toBe(VendorRegime::Igs)
        ->and($r('ORGANISME À BUT NON LUCRATIF'))->toBe(VendorRegime::NonLucratif)
        ->and($r('NON PROFESSIONNEL'))->toBe(VendorRegime::NonProfessionnel)
        ->and($r("HORS REGIME D'IMPOSITION"))->toBe(VendorRegime::HorsRegime)
        ->and($r(''))->toBe(VendorRegime::Unknown)
        ->and($r(null))->toBe(VendorRegime::Unknown)
        ->and($r('AUTRE CHOSE'))->toBe(VendorRegime::Unknown);
});

it('maps each régime to VAT applicability', function () {
    expect(VendorRegime::Reel->vatApplicable())->toBeTrue()
        ->and(VendorRegime::Igs->vatApplicable())->toBeFalse()
        ->and(VendorRegime::Simplifie->vatApplicable())->toBeFalse()
        ->and(VendorRegime::NonLucratif->vatApplicable())->toBeFalse()
        ->and(VendorRegime::NonProfessionnel->vatApplicable())->toBeFalse()
        ->and(VendorRegime::HorsRegime->vatApplicable())->toBeFalse()
        ->and(VendorRegime::Unknown->vatApplicable())->toBeNull();
});

/*
 * User-attested VAT exemption: an invoice with NO VAT and NO exemption mention is
 * not auto-failed — the user decides (per the CGI) whether the operation is
 * exempt. Attested -> treated as exempt; not attested -> the VAT checks fail.
 */

it('fails the VAT mentions when no VAT, no exemption mention and no attestation', function () {
    $noVat = invoice(['vatRate' => null, 'vatAmount' => null, 'ttcAmount' => 1_000_000]);

    $result = $this->checker->check($noVat);

    expect($result->conform)->toBeFalse()
        ->and(collect($result->failed())->pluck('key'))->toContain('vat');
});

it('treats the invoice as exempt when the user attests the operation is VAT-exempt', function () {
    $noVat = invoice(['vatRate' => null, 'vatAmount' => null, 'ttcAmount' => 1_000_000]);

    $result = $this->checker->check($noVat, userAttestedVatExempt: true);

    expect($result->conform)->toBeTrue();

    $vatCalc = collect($result->findings)->firstWhere('key', 'vat_calc');
    expect($vatCalc->detail)->toBe(__('app.conformity.narr.vat_attested')); // audit trail says WHO decided
});

it('rejects a contradictory attestation (invoice still carries VAT)', function () {
    // Ticking "exempt" cannot whitewash an invoice that DOES bill VAT.
    $result = $this->checker->check(invoice(), userAttestedVatExempt: true);

    expect($result->conform)->toBeFalse()
        ->and(collect($result->failed())->pluck('key'))->toContain('vat_calc');
});

it('does not let attestation bypass the régime rule when VAT is billed', function () {
    $result = $this->checker->check(invoice(), vendorRegime: 'IGS', userAttestedVatExempt: true);

    expect($result->conform)->toBeFalse();
});
