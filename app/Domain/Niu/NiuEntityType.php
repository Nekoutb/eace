<?php

declare(strict_types=1);

namespace App\Domain\Niu;

/**
 * The first character of a NIU encodes the taxpayer type.
 * P = personne physique, M = personne morale (CGI 2025, art. 8 du décret
 * du 13/11/2012 ; cf. article Fiscalis « vérifier la première lettre »).
 */
enum NiuEntityType: string
{
    case PersonnePhysique = 'P';
    case PersonneMorale = 'M';

    public function label(): string
    {
        return match ($this) {
            self::PersonnePhysique => 'Personne physique',
            self::PersonneMorale => 'Personne morale',
        };
    }
}
