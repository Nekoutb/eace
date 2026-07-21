<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

/**
 * The families of invoice requirements checked at recording:
 * forme = the mandatory art. 150 mentions are present; fond = they are coherent
 * and valid; regime = the vendor's régime governs whether VAT may appear at all
 * (a VAT-liability question, NOT an art. 150 condition).
 */
enum ConformityType: string
{
    case Forme = 'forme';
    case Fond = 'fond';
    case Regime = 'regime';

    public function label(): string
    {
        return match ($this) {
            self::Forme => (string) __('app.conformity.type_forme'),
            self::Fond => (string) __('app.conformity.type_fond'),
            self::Regime => (string) __('app.conformity.type_regime'),
        };
    }
}
