<?php

declare(strict_types=1);

namespace App\Domain\Risk\Rules;

use App\Domain\Risk\RiskRule;
use App\Domain\Risk\RuleFinding;
use App\Domain\Risk\RuleSeverity;
use App\Domain\Risk\VerificationContext;

/**
 * R11 — a foreign supplier. The Fiscalis file only covers Cameroonian NIUs; a
 * foreign supplier is outside it. Reminders: withhold the 15 % TSR on the gross
 * and self-assess VAT if the service is consumed in Cameroon. The exposure here
 * is the 15 % you must withhold and remit.
 */
final class ForeignSupplierRule implements RiskRule
{
    private const TSR_RATE = 0.15;

    public function evaluate(VerificationContext $context): ?RuleFinding
    {
        if (! $context->foreignSupplier) {
            return null;
        }

        $amount = $context->invoiceAmountXaf;
        $tsr = ($amount !== null && $amount > 0)
            ? (int) round($amount * self::TSR_RATE)
            : 0;

        return new RuleFinding(
            code: 'R11',
            title: 'Fournisseur étranger',
            article: 'CGI (TSR 15 %, autoliquidation TVA)',
            message: 'Fournisseur étranger hors fichier camerounais : opérez la TSR de 15 % '
                .'sur le brut versé et auto-liquidez la TVA si le service est consommé au '
                .'Cameroun. Conservez le contrat et les justificatifs.',
            severity: RuleSeverity::Warning,
            exposureXaf: $tsr,
        );
    }
}
