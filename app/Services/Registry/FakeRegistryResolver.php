<?php

declare(strict_types=1);

namespace App\Services\Registry;

use App\Domain\Niu\FiscalisStatus;
use App\Domain\Niu\NiuEntityType;
use App\Domain\Niu\NiuNumber;
use App\Domain\Risk\RegistryRecord;
use App\Domain\Risk\RegistryResolver;

/**
 * Stand-in for the Fiscalis portal until the real connector lands. Resolves a
 * small set of known demo NIUs across the verdict spectrum; any other valid NIU
 * resolves to null (introuvable). Seeded entries can be overridden in tests.
 */
final class FakeRegistryResolver implements RegistryResolver
{
    /**
     * @var array<string, RegistryRecord>
     */
    private array $records;

    /**
     * @param  array<string, RegistryRecord>|null  $records
     */
    public function __construct(?array $records = null)
    {
        $this->records = $records ?? self::seed();
    }

    public function resolve(NiuNumber $niu): ?RegistryRecord
    {
        return $this->records[(string) $niu] ?? null;
    }

    /**
     * @return array<string, RegistryRecord>
     */
    private static function seed(): array
    {
        return [
            'M058712345678X' => new RegistryRecord(
                taxpayerName: 'SARL EXEMPLE',
                status: FiscalisStatus::Actif,
                onActiveFile: true,
                entityType: NiuEntityType::PersonneMorale,
                regime: 'Réel',
                centre: 'CIME Douala',
                activity: '46900 — Commerce de gros',
            ),
            'P058712345678X' => new RegistryRecord(
                taxpayerName: 'EKANE JOSEPH BERTRAND',
                status: FiscalisStatus::Actif,
                onActiveFile: true,
                entityType: NiuEntityType::PersonnePhysique,
                regime: 'Simplifié',
                centre: 'CDI Bafoussam',
                activity: '47190 — Commerce de détail',
            ),
            'M111111111111A' => new RegistryRecord(
                taxpayerName: 'ETS SUSPENDU',
                status: FiscalisStatus::Suspendu,
                onActiveFile: false,
                entityType: NiuEntityType::PersonneMorale,
                regime: 'Réel',
                centre: 'CIME Yaoundé',
            ),
            'M222222222222A' => new RegistryRecord(
                taxpayerName: 'SARL RADIEE',
                status: FiscalisStatus::Radie,
                onActiveFile: false,
                entityType: NiuEntityType::PersonneMorale,
                regime: 'Réel',
                centre: 'CIME Douala',
            ),
        ];
    }
}
