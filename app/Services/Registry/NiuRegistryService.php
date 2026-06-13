<?php

declare(strict_types=1);

namespace App\Services\Registry;

use App\Domain\Niu\NiuNumber;
use App\Domain\Risk\RegistryRecord;
use App\Domain\Risk\RegistryResolver;
use App\Models\NiuRegistry;
use App\Models\NiuRegistrySnapshot;

/**
 * Owns the shared central registry: resolves a NIU (today via the fake resolver,
 * tomorrow via Fiscalis), refreshes the cache row, and appends an immutable,
 * hash-chained snapshot — the evidence of what the file held at that instant.
 */
final class NiuRegistryService
{
    public function __construct(private RegistryResolver $resolver) {}

    public function lookup(NiuNumber $niu): RegistryLookup
    {
        $record = $this->resolver->resolve($niu);

        if ($record === null) {
            return new RegistryLookup(null, null);
        }

        $this->cache($niu, $record);
        $snapshot = $this->snapshot($niu, $record);

        return new RegistryLookup($record, $snapshot->id);
    }

    private function cache(NiuNumber $niu, RegistryRecord $record): void
    {
        NiuRegistry::query()->updateOrCreate(
            ['niu' => (string) $niu],
            [
                'entity_type' => $record->entityType,
                'taxpayer_name' => $record->taxpayerName,
                'sigle' => $record->sigle,
                'regime' => $record->regime,
                'centre' => $record->centre,
                'activity' => $record->activity,
                'fiscalis_status' => $record->status->value,
                'on_active_file' => $record->onActiveFile,
                'source' => 'fake',
                'last_checked_at' => now(),
            ],
        );
    }

    private function snapshot(NiuNumber $niu, RegistryRecord $record): NiuRegistrySnapshot
    {
        $payload = $this->payload($record);
        $previousHash = NiuRegistrySnapshot::query()
            ->where('niu', (string) $niu)
            ->latest('fetched_at')
            ->value('content_hash');

        return NiuRegistrySnapshot::query()->create([
            'niu' => (string) $niu,
            'payload' => $payload,
            'content_hash' => NiuRegistrySnapshot::hashPayload($payload),
            'previous_hash' => $previousHash,
            'source' => 'fake',
            'fetched_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RegistryRecord $record): array
    {
        return [
            'taxpayer_name' => $record->taxpayerName,
            'sigle' => $record->sigle,
            'fiscalis_status' => $record->status->value,
            'on_active_file' => $record->onActiveFile,
            'entity_type' => $record->entityType?->value,
            'regime' => $record->regime,
            'centre' => $record->centre,
            'activity' => $record->activity,
        ];
    }
}
