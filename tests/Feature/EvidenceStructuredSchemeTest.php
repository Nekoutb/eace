<?php

declare(strict_types=1);

use App\Domain\Approval\VerificationStage;
use App\Models\AcfCertificate;
use App\Models\Tenant;
use App\Models\Verification;
use App\Services\Evidence\EvidenceContext;
use App\Services\Evidence\EvidenceNaming;
use App\Services\Evidence\EvidenceStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'eace']);
    tenancy()->initialize($this->tenant);
    Storage::fake('local');
    config(['niu.wasabi.enabled' => false]); // exercise the local fallback
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function evAt(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-06-21 14:25:30');
}

it('stores evidence under the human-browsable structured key and round-trips', function () {
    $context = EvidenceContext::make('recording', 'M120000004821S', 'Ste COFITRAD', 'FAC-2026-001', evAt());

    $ref = (new EvidenceStore)->putEvidence($context, 'Invoice_FAC-2026-001_2026-06-21_142530.pdf', 'BYTES', EvidenceStore::TYPE_INVOICES);

    expect($ref)->toBe('local::eace/2026/suppliers/M120000004821S__Ste_COFITRAD/FAC-2026-001/Invoice_FAC-2026-001_2026-06-21_142530.pdf')
        ->and((new EvidenceStore)->get($ref))->toBe('BYTES');
});

it('derives a per-invoice recording context from a verification', function () {
    $verification = new Verification;
    $verification->forceFill([
        'niu' => 'M120000004821S',
        'raw_input' => 'M120000004821S',
        'stage' => VerificationStage::Recording,
        'invoice_data' => ['invoice_number' => 'FAC-2026-001', 'supplier_name' => 'Sté COFITRAD & Cie'],
    ]);

    $context = EvidenceContext::forVerification($verification, evAt());

    expect($context->phase)->toBe('recording')
        ->and($context->leaf)->toBe('FAC-2026-001')
        ->and($context->supplierSegment())->toBe('M120000004821S__Ste_COFITRAD_Cie')
        ->and($context->year())->toBe('2026')
        ->and($context->stamp())->toBe('2026-06-21_142530');
});

it('files a settlement verification under settlements/<payment date>', function () {
    $verification = new Verification;
    $verification->forceFill(['niu' => 'M120000004821S', 'raw_input' => 'M120000004821S', 'stage' => VerificationStage::Settlement]);

    $context = EvidenceContext::forVerification($verification, CarbonImmutable::parse('2026-06-25 10:15:00'));

    expect($context->phase)->toBe('settlement')
        ->and($context->leaf)->toBe('settlements/2026-06-25')
        ->and($context->supplierSegment())->toBe('M120000004821S');
});

it('files an ACF under the vendor acf-<reference> folder', function () {
    $acf = new AcfCertificate;
    $acf->forceFill(['reference' => '620123456', 'niu' => 'M120000004821S']);

    $context = EvidenceContext::forAcf($acf, evAt());

    expect($context->leaf)->toBe('acf-620123456')
        ->and($context->supplierSegment())->toBe('M120000004821S');
});

it('falls back to invoice-<id> when the invoice number is missing', function () {
    $verification = new Verification;
    $verification->forceFill(['id' => 77, 'niu' => 'M1', 'raw_input' => 'M1', 'stage' => VerificationStage::Recording, 'invoice_data' => []]);

    expect(EvidenceContext::forVerification($verification, evAt())->leaf)->toBe('invoice-77');
});

it('builds the descriptive filenames per the convention', function () {
    $context = EvidenceContext::make('recording', 'M120000004821S', 'Ste COFITRAD', 'FAC-1', evAt());

    expect(EvidenceNaming::acfPdf($context))->toBe('ACF_Ste_COFITRAD_2026-06-21_142530.pdf')
        ->and(EvidenceNaming::acfAuthentic('620123456', $context))->toBe('ACF_Authentic_620123456_2026-06-21_142530.png')
        ->and(EvidenceNaming::niuStatus('present', 'M120000004821S', $context))->toBe('NIU_present_M120000004821S_2026-06-21_142530.png')
        ->and(EvidenceNaming::taxRegister('2026-06-19', $context))->toBe('TaxRegister_2026-06-19_Ste_COFITRAD_2026-06-21_142530.png')
        ->and(EvidenceNaming::invoice('FAC-2026-001', 'pdf', $context))->toBe('Invoice_FAC-2026-001_2026-06-21_142530.pdf');
});

it('sanitises vendor segments: accents folded, punctuation collapsed', function () {
    expect(EvidenceStore::sanitize('Sté COFITRAD & Cie, SARL'))->toBe('Ste_COFITRAD_Cie_SARL')
        ->and(EvidenceStore::sanitize('ETS NDONGO / FILS'))->toBe('ETS_NDONGO_FILS')
        ->and(EvidenceStore::sanitize('Éç—Œ'))->toBe('Ec_OE')
        ->and(EvidenceStore::sanitize('', 'vendor'))->toBe('vendor')
        ->and(EvidenceStore::sanitize(null, 'x'))->toBe('x');
});

it('co-locates the timestamp seal beside the evidence it seals', function () {
    $context = EvidenceContext::make('recording', 'M120000004821S', 'Vendor', 'FAC-1', evAt());
    $evidence = (new EvidenceStore)->putEvidence($context, 'NIU_present_M120_2026-06-21_142530.png', 'IMG', EvidenceStore::TYPE_EVIDENCE);

    $seal = (new EvidenceStore)->putSeal($evidence, 'TOKEN');

    expect($seal)->toBe($evidence.'.tsr')
        ->and((new EvidenceStore)->get($seal))->toBe('TOKEN');
});

it('lists a vendor+invoice folder for querying', function () {
    $context = EvidenceContext::make('recording', 'M120000004821S', 'Ste COFITRAD', 'FAC-2026-001', evAt());
    $store = new EvidenceStore;
    $store->putEvidence($context, 'Invoice_FAC-2026-001_2026-06-21_142530.pdf', 'A', EvidenceStore::TYPE_INVOICES);
    $store->putEvidence($context, 'NIU_present_M120_2026-06-21_142530.png', 'B', EvidenceStore::TYPE_EVIDENCE);

    $prefix = $store->prefixFor($context);

    expect($prefix)->toBe('eace/2026/suppliers/M120000004821S__Ste_COFITRAD/FAC-2026-001/')
        ->and($store->listKeys($prefix))->toHaveCount(2);
});

it('stages transient bytes on the local disk and can prune them', function () {
    $store = new EvidenceStore;
    $ref = $store->putStaging('national-file.png', 'IMG');

    expect($ref)->toStartWith('local::staging/')
        ->and($store->get($ref))->toBe('IMG');

    $store->delete($ref);
    expect($store->exists($ref))->toBeFalse();
});
