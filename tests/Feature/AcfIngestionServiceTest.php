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
