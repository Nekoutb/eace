<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Services\Evidence\EvidenceStore;
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

it('stores under a tenant-prefixed, date-partitioned key and round-trips', function () {
    $ref = (new EvidenceStore)->put(EvidenceStore::TYPE_EVIDENCE, 10487, 'fiscalis.png', 'BYTES');

    expect($ref)->toStartWith('local::t/eace/evidence/')
        ->and($ref)->toMatch('#t/eace/evidence/\d{4}/\d{2}/v_10487/#');

    $store = new EvidenceStore;
    expect($store->exists($ref))->toBeTrue()
        ->and($store->get($ref))->toBe('BYTES');
});

it('confines the key to the active tenant prefix per doc type', function () {
    expect((new EvidenceStore)->put(EvidenceStore::TYPE_INVOICES, 1, 'inv.pdf', 'X'))
        ->toContain('t/eace/invoices/');
});

it('falls back to local storage when Wasabi is disabled', function () {
    $ref = (new EvidenceStore)->put(EvidenceStore::TYPE_ACF, 5, 'a.png', 'X');

    expect($ref)->toStartWith('local::');
    [$disk, $key] = (new EvidenceStore)->resolve($ref);
    expect($disk)->toBe('local');
    Storage::disk('local')->assertExists($key);
});

it('treats a legacy bare path as a local file', function () {
    Storage::disk('local')->put('fiscalis-snapshots/old.png', 'LEGACY');
    $store = new EvidenceStore;

    expect($store->resolve('fiscalis-snapshots/old.png'))->toBe(['local', 'fiscalis-snapshots/old.png'])
        ->and($store->get('fiscalis-snapshots/old.png'))->toBe('LEGACY')
        ->and($store->exists('fiscalis-snapshots/old.png'))->toBeTrue();
});

it('builds a base64 data-URI for inline display', function () {
    $ref = (new EvidenceStore)->put(EvidenceStore::TYPE_EVIDENCE, 1, 'p.png', 'IMG');

    expect((new EvidenceStore)->dataUri($ref))->toBe('data:image/png;base64,'.base64_encode('IMG'))
        ->and((new EvidenceStore)->dataUri(null))->toBeNull();
});

it('serves a display URL — local files inline, missing refs null (no blocking read on render)', function () {
    // With Wasabi off, a local/legacy file falls back to an inline data-URI; in
    // production a wasabi:: ref yields a short-lived presigned URL instead, so the
    // page render never reads (or base64-inlines) a multi-MB image.
    $ref = (new EvidenceStore)->put(EvidenceStore::TYPE_EVIDENCE, 1, 'p.png', 'IMG');

    expect((new EvidenceStore)->displayUrl($ref))->toBe('data:image/png;base64,'.base64_encode('IMG'))
        ->and((new EvidenceStore)->displayUrl(null))->toBeNull();
});
