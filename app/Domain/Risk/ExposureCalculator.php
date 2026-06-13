<?php

declare(strict_types=1);

namespace App\Domain\Risk;

/**
 * The CGI tax math in one place. Every figure is an *upper-bound estimate* of
 * what a NIU defect could cost on a given transaction — the point is to turn
 * an abstract compliance risk into a number that motivates action, not to
 * produce an exact assessment. All amounts are whole FCFA (XAF has no decimals).
 *
 * Rates and fines (CGI 2025):
 *  - TVA 19,25 % (taux de droit commun)
 *  - IS ~33 % (impôt sur les sociétés, CAC inclus) — impact d'une charge rejetée
 *  - Majoration 100 % en cas de mauvaise foi (art. L 96)
 *  - Amende 1 000 000 F par opération pour usage frauduleux d'un NIU (art. L 100 (3))
 *  - Amende 100 % de la transaction, minimum 100 000 F, facture erronée (art. L 102)
 */
final class ExposureCalculator
{
    public const VAT_RATE = 0.1925;

    public const IS_RATE = 0.33;

    public const MAJORATION_MAUVAISE_FOI = 1.00;

    public const AMENDE_NIU_FRAUDULEUX = 1_000_000;

    public const AMENDE_FACTURE_MIN = 100_000;

    public const SEUIL_ESPECES = 100_000; // art. 143 (1)(d)

    /** VAT that becomes non-deductible when the supplier/invoice is defective. */
    public function vatLoss(?int $amountXaf): int
    {
        return $this->pct($amountXaf, self::VAT_RATE);
    }

    /** IS impact of an expense rejected as non-deductible. */
    public function chargeLoss(?int $amountXaf): int
    {
        return $this->pct($amountXaf, self::IS_RATE);
    }

    /** Bad-faith surcharge (art. L 96) applied on a reassessed amount of tax. */
    public function badFaithMajoration(int $taxXaf): int
    {
        return (int) round($taxXaf * self::MAJORATION_MAUVAISE_FOI);
    }

    /** Flat fine per operation for fraudulent NIU use (art. L 100 (3)). */
    public function niuFraudFine(): int
    {
        return self::AMENDE_NIU_FRAUDULEUX;
    }

    /** Erroneous/incomplete invoice fine: 100 % of the transaction, min 100 000 F (art. L 102). */
    public function invoiceFine(?int $amountXaf): int
    {
        return max(self::AMENDE_FACTURE_MIN, (int) round((float) ($amountXaf ?? 0)));
    }

    /** Whether a cash payment crosses the deductibility threshold (art. 143 (1)(d)). */
    public function cashThresholdBreached(?int $amountXaf): bool
    {
        return $amountXaf !== null && $amountXaf >= self::SEUIL_ESPECES;
    }

    /**
     * Full exposure when an invoice's VAT and charge are both rejected and the
     * lost VAT is reassessed in bad faith: VAT loss + IS loss + 100 % majoration
     * on the VAT.
     */
    public function rejectedInvoiceExposure(?int $amountXaf): int
    {
        $vat = $this->vatLoss($amountXaf);

        return $vat + $this->chargeLoss($amountXaf) + $this->badFaithMajoration($vat);
    }

    private function pct(?int $amountXaf, float $rate): int
    {
        if ($amountXaf === null || $amountXaf <= 0) {
            return 0;
        }

        return (int) round($amountXaf * $rate);
    }
}
