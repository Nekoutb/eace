<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

/**
 * The exemption mention an invoice may carry per product (CGI art. 150):
 * « Exonérée » or « prise en charge État ». None means the line is taxable.
 */
enum ExemptionMention: string
{
    case None = 'none';
    case Exonere = 'exonere';
    case PriseEnChargeEtat = 'prise_en_charge_etat';

    public function label(): string
    {
        return match ($this) {
            self::None => (string) __('app.exemption.none'),
            self::Exonere => (string) __('app.exemption.exonere'),
            self::PriseEnChargeEtat => (string) __('app.exemption.prise_en_charge'),
        };
    }
}
