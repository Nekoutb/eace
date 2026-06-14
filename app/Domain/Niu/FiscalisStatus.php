<?php

declare(strict_types=1);

namespace App\Domain\Niu;

/**
 * The "Statut" column of the Fiscalis taxpayer file. A radié NIU makes any
 * invoice issued after the radiation date fiscally void; a suspendu NIU should
 * stop new payments until it returns to actif (see the article's status table).
 */
enum FiscalisStatus: string
{
    case Actif = 'actif';
    case Suspendu = 'suspendu';
    case Radie = 'radie';

    public function label(): string
    {
        return (string) __('app.fiscalis_status.'.$this->value);
    }

    public function isActive(): bool
    {
        return $this === self::Actif;
    }
}
