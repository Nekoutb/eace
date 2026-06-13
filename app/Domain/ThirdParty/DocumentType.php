<?php

declare(strict_types=1);

namespace App\Domain\ThirdParty;

/**
 * Compliance documents collected during supplier on-boarding (the article's
 * KYC/LCB-FT checklist). ACF carries an expiry (3 months, art. L 94) the
 * monitoring layer counts down.
 */
enum DocumentType: string
{
    case Acf = 'acf';   // Attestation de Conformité Fiscale
    case Rccm = 'rccm'; // Registre du Commerce et du Crédit Mobilier
    case Cnps = 'cnps'; // attestation CNPS
    case Rib = 'rib';   // relevé d'identité bancaire
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Acf => 'Attestation de Conformité Fiscale',
            self::Rccm => 'RCCM',
            self::Cnps => 'Attestation CNPS',
            self::Rib => 'RIB',
            self::Other => 'Autre',
        };
    }

    public function expires(): bool
    {
        return $this === self::Acf;
    }
}
