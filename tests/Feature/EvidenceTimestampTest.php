<?php

declare(strict_types=1);

use App\Domain\Evidence\TimestampStatus;
use App\Models\EvidenceTimestamp;
use App\Models\Tenant;
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

function sealService(): EvidenceTimestampService
{
    return new EvidenceTimestampService(new FakeTimestampAuthority);
}

it('seals a stored capture with a trusted timestamp when enabled', function () {
    config(['niu.timestamp.enabled' => true]);

    Storage::disk('local')->put('acf-snapshots/shot.png', 'PNG-BYTES');

    $row = sealService()->seal('local', 'acf-snapshots/shot.png', 'PNG-BYTES');

    expect($row->status)->toBe(TimestampStatus::Signed)
        ->and($row->sha256)->toBe(hash('sha256', 'PNG-BYTES'))
        ->and($row->token_path)->toBe('acf-snapshots/shot.png.tsr')
        ->and($row->timestamped_at)->not->toBeNull();

    // The signed token is persisted next to the evidence image.
    Storage::disk('local')->assertExists('acf-snapshots/shot.png.tsr');
});

it('records a disabled seal without contacting any authority', function () {
    config(['niu.timestamp.enabled' => false]);

    $row = sealService()->seal('local', 'fiscalis-snapshots/x.png', 'BYTES');

    expect($row->status)->toBe(TimestampStatus::Disabled)
        ->and($row->token_path)->toBeNull()
        ->and($row->sha256)->toBe(hash('sha256', 'BYTES'));
    Storage::disk('local')->assertMissing('fiscalis-snapshots/x.png.tsr');
});

it('retries seals left pending by an authority outage', function () {
    config(['niu.timestamp.enabled' => true]);

    // A capture whose seal could not be obtained at the time.
    $row = EvidenceTimestamp::query()->create([
        'disk' => 'local',
        'path' => 'acf-snapshots/late.png',
        'sha256' => hash('sha256', 'LATE'),
        'status' => TimestampStatus::Pending,
        'attempts' => 1,
    ]);

    $signed = sealService()->retryPending();

    expect($signed)->toBe(1)
        ->and($row->fresh()->status)->toBe(TimestampStatus::Signed)
        ->and($row->fresh()->attempts)->toBe(2);
});

it('does not retry a seal that already hit the attempts ceiling', function () {
    config(['niu.timestamp.enabled' => true, 'niu.timestamp.max_attempts' => 5]);

    $row = EvidenceTimestamp::query()->create([
        'disk' => 'local',
        'path' => 'acf-snapshots/maxed.png',
        'sha256' => hash('sha256', 'X'),
        'status' => TimestampStatus::Failed,
        'attempts' => 5,
    ]);

    expect(sealService()->retryPending())->toBe(0)
        ->and($row->fresh()->status)->toBe(TimestampStatus::Failed)
        ->and($row->fresh()->attempts)->toBe(5); // never re-attempted
});

it('never retries a disabled seal even after timestamping is enabled', function () {
    config(['niu.timestamp.enabled' => false]);
    $row = sealService()->seal('local', 'fiscalis-snapshots/d.png', 'B');
    expect($row->status)->toBe(TimestampStatus::Disabled);

    config(['niu.timestamp.enabled' => true]);
    sealService()->retryPending();

    expect($row->fresh()->status)->toBe(TimestampStatus::Disabled)
        ->and($row->fresh()->attempts)->toBe(0);
});

it('strips control characters from the capture band so it cannot be spoofed', function () {
    $band = CaptureStamp::for("620123\n\nAUTHENTIC: YES\t<x>");

    expect($band)->not->toContain("\n")
        ->and($band)->not->toContain("\t")
        ->and($band)->toContain('620123'); // subject kept, but on one line only
});

it('looks a capture seal up by its stored path', function () {
    config(['niu.timestamp.enabled' => true]);
    sealService()->seal('local', 'fiscalis-snapshots/y.png', 'Y');

    expect(EvidenceTimestamp::forPath('fiscalis-snapshots/y.png'))->not->toBeNull()
        ->and(EvidenceTimestamp::forPath(null))->toBeNull()
        ->and(EvidenceTimestamp::forPath('does/not/exist.png'))->toBeNull();
});

it('rejects an invalid digest without reaching the network', function () {
    $authority = new Rfc3161TimestampAuthority('https://freetsa.org/tsr');

    $result = $authority->timestamp('not-a-valid-sha256');

    expect($result->status)->toBe(TimestampStatus::Failed)
        ->and($result->isSigned())->toBeFalse();
});

it('stamps every capture with a dated, subject-bearing band', function () {
    $band = CaptureStamp::for('https://fanr.harmony2.cm/authentification?reference=620123');

    expect($band)->toContain('620123')
        ->and($band)->toContain('capture')
        ->and($band)->toMatch('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/');
});
