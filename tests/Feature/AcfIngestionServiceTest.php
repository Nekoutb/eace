<?php

declare(strict_types=1);

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfStatus;
use App\Domain\Acf\UnreadableAcfException;
use App\Models\AcfCertificate;
use App\Models\Tenant;
use App\Services\Acf\AcfIngestionService;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'acme']);
    tenancy()->initialize($this->tenant);
    $this->service = app(AcfIngestionService::class);
    $this->text = file_get_contents(base_path('tests/Fixtures/acf/express-union-acf.txt'));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('ingests the real ACF into a monitored certificate', function () {
    $certificate = $this->service->ingestFromText($this->text, ['created_by' => 1]);

    expect($certificate->reference)->toBe('620264471967')
        ->and($certificate->niu)->toBe('M020100021296Z')
        ->and($certificate->taxpayer_name)->toBe('EXPRESS UNION FINANCE SA')
        ->and($certificate->issued_at->toDateString())->toBe('2026-03-19')
        ->and($certificate->expires_at->toDateString())->toBe('2026-06-19')
        ->and($certificate->validity_months)->toBe(3)
        ->and($certificate->authenticity_status)->toBe(AcfAuthenticityStatus::Unverified)
        ->and($certificate->status)->toBeInstanceOf(AcfStatus::class);
});

it('persists exactly one row and updates on re-ingest (dedupe on reference + niu)', function () {
    $this->service->ingestFromText($this->text);
    $this->service->ingestFromText($this->text);

    expect(AcfCertificate::query()->count())->toBe(1);
});

it('rejects an unreadable document', function () {
    $this->service->ingestFromText('ceci n’est pas une attestation de conformité');
})->throws(UnreadableAcfException::class);

// ——— One live certificate per vendor: a new upload ALWAYS replaces the registered one. ———

/** The fixture, re-dated and re-referenced (fixture: ref 620264471967, issued 19/03/2026). */
function acfVariant(string $text, string $reference, string $issuedOn): string
{
    return str_replace(['620264471967', '19/03/2026'], [$reference, $issuedOn], $text);
}

it('overwrites the registered certificate in place when a later-validity one arrives', function () {
    $original = $this->service->ingestFromText($this->text);
    $original->forceFill([
        'authenticity_status' => AcfAuthenticityStatus::Authentic,
        'authenticity_snapshot_path' => 'snapshots/old.png',
        'last_verified_at' => now(),
    ])->save();

    $replacement = $this->service->ingestFromText(acfVariant($this->text, '620299999999', '19/05/2026'));

    expect(AcfCertificate::query()->count())->toBe(1)
        // Same row: past verifications keep their acf_certificate_id evidence link.
        ->and($replacement->id)->toBe($original->id)
        ->and($replacement->reference)->toBe('620299999999')
        ->and($replacement->issued_at->toDateString())->toBe('2026-05-19')
        ->and($replacement->expires_at->toDateString())->toBe('2026-08-19')
        // A different physical document: the old authentication no longer applies.
        ->and($replacement->authenticity_status)->toBe(AcfAuthenticityStatus::Unverified)
        ->and($replacement->authenticity_snapshot_path)->toBeNull()
        ->and($replacement->last_verified_at)->toBeNull();
});

it('replaces a still-valid registered certificate with an EARLIER-validity upload (deliberate override)', function () {
    // The registered ACF is still valid, but the user chooses to replace it with
    // a different (even earlier-expiring) one — the latest upload always wins.
    $original = $this->service->ingestFromText($this->text); // issued 19/03/2026 → exp 19/06/2026

    $replacement = $this->service->ingestFromText(acfVariant($this->text, '620211111111', '19/01/2026'));

    expect(AcfCertificate::query()->count())->toBe(1)
        ->and($replacement->id)->toBe($original->id)                 // same row
        ->and($replacement->reference)->toBe('620211111111')         // new document wins
        ->and($replacement->issued_at->toDateString())->toBe('2026-01-19')
        ->and($replacement->authenticity_status)->toBe(AcfAuthenticityStatus::Unverified);
});

it('replaces a certificate with a different one carrying the SAME validity date', function () {
    $original = $this->service->ingestFromText($this->text);

    $replacement = $this->service->ingestFromText(acfVariant($this->text, '620222222222', '19/03/2026'));

    expect(AcfCertificate::query()->count())->toBe(1)
        ->and($replacement->id)->toBe($original->id)
        ->and($replacement->reference)->toBe('620222222222');
});

it('still refreshes a re-upload of the same reference without the validity gate', function () {
    $first = $this->service->ingestFromText($this->text);
    $again = $this->service->ingestFromText($this->text);

    expect(AcfCertificate::query()->count())->toBe(1)
        ->and($again->id)->toBe($first->id)
        ->and($again->reference)->toBe('620264471967');
});
