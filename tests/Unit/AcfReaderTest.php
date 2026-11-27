<?php

declare(strict_types=1);

use App\Domain\Acf\AcfReader;
use App\Domain\Niu\NiuNumber;

beforeEach(function () {
    $this->text = file_get_contents(dirname(__DIR__).'/Fixtures/acf/express-union-acf.txt');
    $this->extract = (new AcfReader)->fromText($this->text);
});

it('reads the ACF reference from the real certificate', function () {
    expect($this->extract->reference)->toBe('620264471967');
});

it('reads and validates the NIU', function () {
    expect($this->extract->niu)->toBeInstanceOf(NiuNumber::class)
        ->and($this->extract->niu->value)->toBe('M020100021296Z');
});

it('reads the taxpayer name without the legal-NIU tail', function () {
    expect($this->extract->taxpayerName)->toBe('EXPRESS UNION FINANCE SA');
});

it('reads the issue date as day-first', function () {
    expect($this->extract->issuedAt?->toDateString())->toBe('2026-03-19');
});

it('reads the printed validity window in months', function () {
    expect($this->extract->validityMonths)->toBe(3);
});

it('reads the régime and centre', function () {
    expect($this->extract->regime)->toBe('RÉEL')
        ->and($this->extract->centre)->toBe('DGE IFU3');
});

it('reads the Harmony2 authenticity URL', function () {
    expect($this->extract->authenticityUrl)
        ->toContain('fanr.harmony2.cm/authentification')
        ->toContain('reference=620264471967');
});

it('detects no moratorium on this certificate', function () {
    expect($this->extract->hasMoratorium)->toBeFalse();
});

it('considers the certificate usable (reference + issue date present)', function () {
    expect($this->extract->isUsable())->toBeTrue();
});

it('returns nulls and not-usable for unrelated text', function () {
    $extract = (new AcfReader)->fromText('ceci n’est pas une attestation');

    expect($extract->reference)->toBeNull()
        ->and($extract->niu)->toBeNull()
        ->and($extract->isUsable())->toBeFalse();
});

it('recovers the 620-prefixed reference even when the label and URL are mangled', function () {
    // No "ACF Reference" label, no authentification URL — only the bare number
    // amid other certificate figures (NIU, phone, P.O. box, dates).
    $text = 'M109400000449K  237696921832  13615  10/04/2026  620264856998  ADC SA';
    $extract = (new AcfReader)->fromText($text);

    expect($extract->reference)->toBe('620264856998');
});

it('does not mistake the phone or NIU digits for the reference', function () {
    $text = 'Téléphone : 237696921832  de NIU: M109400000449K';
    $extract = (new AcfReader)->fromText($text);

    expect($extract->reference)->toBeNull();
});

it('reads a NIU whose final letter the DGI printed in lowercase', function () {
    $text = "Le contribuable : ELITE ADVISORS SARLU de NIU: \nM032618476808k\n"
        ."Réference ACF : 620264954241DOUALA : 27/10/2026\n"
        .'Elle est valable pour une durée de 3 mois à compter du 27/10/2026.';

    $extract = (new AcfReader)->fromText($text);

    expect($extract->niu?->value)->toBe('M032618476808K');
});
