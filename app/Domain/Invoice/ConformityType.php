<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

/**
 * The two families of invoice requirements under CGI art. 150:
 * forme = the mandatory mentions are present; fond = they are coherent and valid.
 */
enum ConformityType: string
{
    case Forme = 'forme';
    case Fond = 'fond';

    public function label(): string
    {
        return match ($this) {
            self::Forme => (string) __('app.conformity.type_forme'),
            self::Fond => (string) __('app.conformity.type_fond'),
        };
    }
}
