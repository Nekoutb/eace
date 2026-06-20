<?php

declare(strict_types=1);

use App\Domain\Evidence\TimestampStatus;
use App\Models\EvidenceTimestamp;
use App\Models\Tenant;
use App\Services\Evidence\EvidenceStore;
use App\Services\Evidence\EvidenceTimestampService;
use App\Services\Evidence\FakeTimestampAuthority;
use App\Services\Evidence\Rfc3161TimestampAuthority;
use App\Support\CaptureStamp;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'ts']);
    tenancy()->initialize($this->tenant);
    Storage::fake('local');
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function evStore(): EvidenceStore
{
    return new EvidenceStore;
}

function sealService(): EvidenceTimestampService
{
    return new EvidenceTimestampService(new FakeTimestampAuthority, new EvidenceStore);
}

it('seals a stored capture with a trusted timestamp when enabled', function () {
    config(['niu.timestamp.enabled' => true]);
    $ref = evStore()->put(EvidenceStore::TYPE_EVIDENCE, 1, 'shot.png', 'PNG-BYTES');

    $row = sealService()->seal($ref, 'PNG-BYTES');

    expect($row->status)->toBe(TimestampStatus::Signed)
        ->and($row->sha256)->toBe(hash('sha256', 'PNG-BYTES'))
        ->and($row->path)->toBe($ref)
        ->and($row->token_path)->not->toBeNull()
        ->and($row->timestamped_at)->not->toBeNull();

    // The signed token is retrievable through the store, co-located with evidence.
    expect(evStore()->get($row->token_path))->not->toBeNull();
});

it('records a disabled seal without contacting any authority', function () {
    config(['niu.timestamp.enabled' => false]);
    $ref = evStore()->put(EvidenceStore::TYPE_EVIDENCE, 2, 'x.png', 'BYTES');

    $row = sealService()->seal($ref, 'BYTES');

    expect($row->status)->toBe(TimestampStatus::Disabled)
        ->and($row->token_path)->toBeNull()
        ->and($row->sha256)->toBe(hash('sha256', 'BYTES'));
});

it('retries seals left pending by an authority outage', function () {
    config(['niu.timestamp.enabled' => true]);

    $row = EvidenceTimestamp::query()->create([
        'disk' => 'local',
        'path' => 'local::t/ts/evidence/2026/06/v_9/late.png',
        'sha256' => hash('sha256', 'LATE'),
        'status' => TimestampStatus::Pending,
        'attempts' => 1,
    ]);

    expect(sealService()->retryPending())->toBe(1)
        ->and($row->fresh()->status)->toBe(TimestampStatus::Signed)
        ->and($row->fresh()->attempts)->toBe(2);
});

it('does not retry a seal that already hit the attempts ceiling', function () {
    config(['niu.timestamp.enabled' => true, 'niu.timestamp.max_attempts' => 5]);

    $row = EvidenceTimestamp::query()->create([
        'disk' => 'local',
        'path' => 'local::maxed.png',
        'sha256' => hash('sha256', 'X'),
        'status' => TimestampStatus::Failed,
        'attempts' => 5,
    ]);

    expect(sealService()->retryPending())->toBe(0)
        ->and($row->fresh()->attempts)->toBe(5);
});

it('never retries a disabled seal even after timestamping is enabled', function () {
    config(['niu.timestamp.enabled' => false]);
    $ref = evStore()->put(EvidenceStore::TYPE_EVIDENCE, 3, 'd.png', 'B');
    $row = sealService()->seal($ref, 'B');
    expect($row->status)->toBe(TimestampStatus::Disabled);

    config(['niu.timestamp.enabled' => true]);
    sealService()->retryPending();

    expect($row->fresh()->status)->toBe(TimestampStatus::Disabled)
        ->and($row->fresh()->attempts)->toBe(0);
});

it('looks a capture seal up by its stored reference', function () {
    config(['niu.timestamp.enabled' => true]);
    $ref = evStore()->put(EvidenceStore::TYPE_EVIDENCE, 4, 'y.png', 'Y');
    sealService()->seal($ref, 'Y');

    expect(EvidenceTimestamp::forPath($ref))->not->toBeNull()
        ->and(EvidenceTimestamp::forPath(null))->toBeNull();
});

it('rejects an invalid digest without reaching the network', function () {
    $result = (new Rfc3161TimestampAuthority('https://freetsa.org/tsr'))->timestamp('not-a-valid-sha256');

    expect($result->status)->toBe(TimestampStatus::Failed)
        ->and($result->isSigned())->toBeFalse();
});

it('stamps every capture with a dated, subject-bearing band, free of injected lines', function () {
    $band = CaptureStamp::for("620123\n\nAUTHENTIC: YES\t<x>");

    expect($band)->not->toContain("\n")
        ->not->toContain("\t")
        ->toContain('620123')
        ->toMatch('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/');
});
