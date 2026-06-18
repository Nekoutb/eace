<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Services\Evidence\EvidenceStorageService;
use Illuminate\Support\Facades\Storage;

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each->delete();
});

it('sums the evidence files on the tenant disk and reports the quota percentage', function () {
    $tenant = Tenant::create(['id' => 'store']);
    tenancy()->initialize($tenant);
    Storage::fake('local');

    Storage::disk('local')->put('acf-snapshots/a.png', str_repeat('x', 300 * 1024));      // 300 KB
    Storage::disk('local')->put('fiscalis-snapshots/b.png', str_repeat('x', 212 * 1024));  // 212 KB

    $service = new EvidenceStorageService;

    expect($service->usedBytes())->toBe(524_288)        // 512 KB
        ->and($service->percentUsed(1))->toBe(50)       // 512 KB ≈ 50% of 1 MB
        ->and($service->percentUsed(0))->toBe(0);       // guards against divide-by-zero
});

it('falls back to the configured default quota when none is set', function () {
    $tenant = Tenant::create(['id' => 'store']);

    expect($tenant->allocatedSpaceMb())->toBe((int) config('niu.default_allocated_space_mb', 1024));
});
