<?php

declare(strict_types=1);

namespace App\Services\Registry;

use App\Domain\Niu\FiscalisStatus;
use App\Domain\Niu\NiuNumber;
use App\Domain\Risk\RegistryRecord;
use App\Domain\Risk\RegistryResolver;
use App\Models\TaxpayerFileExtraction;
use App\Models\TaxpayerFileRecord;

/**
 * Resolves a NIU against the ingested DGI taxpayer file. Presence in the
 * published "fichier des contribuables" means the NIU is registered and on the
 * active file (status Actif, on_active_file true) — the file does not encode
 * Suspendu/Radié, which the live status connector refines later. A NIU absent
 * from the file resolves to null (introuvable). The record carries the source
 * extraction label so the snapshot evidence names which file it was checked against.
 */
final class TaxpayerFileResolver implements RegistryResolver
{
    private bool $sourceLoaded = false;

    private ?string $source = null;

    public function resolve(NiuNumber $niu): ?RegistryRecord
    {
        $row = TaxpayerFileRecord::query()->where('niu', (string) $niu)->first();

        if ($row === null) {
            return null;
        }

        return new RegistryRecord(
            taxpayerName: $row->raison_sociale,
            status: FiscalisStatus::Actif,
            onActiveFile: true,
            entityType: $niu->entityType(),
            regime: $row->regime,
            sigle: $row->sigle,
            centre: $row->centre,
            activity: $row->activite,
            source: $this->source(),
        );
    }

    private function source(): string
    {
        if (! $this->sourceLoaded) {
            $extraction = TaxpayerFileExtraction::current();
            $this->source = $extraction !== null ? $extraction->period_label : 'taxpayer_file';
            $this->sourceLoaded = true;
        }

        return $this->source ?? 'taxpayer_file';
    }
}
