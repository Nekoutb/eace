<?php

declare(strict_types=1);

use App\Domain\Niu\NiuEntityType;
use App\Models\NiuRegistry;
use App\Models\NiuRegistrySnapshot;
use Illuminate\Support\Carbon;

it('resolves the registry on the central connection', function () {
    expect((new NiuRegistry)->getConnectionName())
        ->toBe(config('tenancy.database.central_connection'));
});

it('persists and casts a registry row', function () {
    $row = NiuRegistry::create([
        'niu' => 'M058712345678X',
        'entity_type' => NiuEntityType::PersonneMorale,
        'taxpayer_name' => 'SARL EXEMPLE',
        'regime' => 'Réel',
        'fiscalis_status' => 'Actif',
        'on_active_file' => true,
        'last_checked_at' => Carbon::parse('2026-06-13 10:00:00'),
    ]);

    $fresh = $row->fresh();

    expect($fresh->entity_type)->toBe(NiuEntityType::PersonneMorale)
        ->and($fresh->on_active_file)->toBeTrue()
        ->and($fresh->last_checked_at)->toBeInstanceOf(Carbon::class);
});

it('hashes a payload deterministically regardless of key order', function () {
    $a = NiuRegistrySnapshot::hashPayload(['niu' => 'M058712345678X', 'status' => 'Actif']);
    $b = NiuRegistrySnapshot::hashPayload(['status' => 'Actif', 'niu' => 'M058712345678X']);

    expect($a)->toBe($b)->and($a)->toHaveLength(64);
});

it('stores an immutable snapshot with a uuid and no updated_at', function () {
    $snapshot = NiuRegistrySnapshot::create([
        'niu' => 'M058712345678X',
        'payload' => ['taxpayer_name' => 'SARL EXEMPLE', 'fiscalis_status' => 'Actif'],
        'content_hash' => str_repeat('a', 64),
        'fetched_at' => now(),
    ]);

    expect($snapshot->id)->toBeString()->toHaveLength(36)
        ->and($snapshot->payload)->toHaveKey('fiscalis_status')
        ->and($snapshot->updated_at)->toBeNull();
});

it('links snapshots to a registry row by niu', function () {
    NiuRegistry::create(['niu' => 'P058712345678X']);
    NiuRegistrySnapshot::create([
        'niu' => 'P058712345678X',
        'payload' => ['fiscalis_status' => 'Actif'],
        'content_hash' => str_repeat('b', 64),
        'fetched_at' => now(),
    ]);

    $row = NiuRegistry::where('niu', 'P058712345678X')->first();

    expect($row->snapshots)->toHaveCount(1);
});
