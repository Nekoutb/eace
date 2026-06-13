<?php

declare(strict_types=1);

namespace App\Domain\Risk\Rules;

use App\Domain\Risk\ExposureCalculator;
use App\Domain\Risk\RiskRule;
use App\Domain\Risk\RuleFinding;
use App\Domain\Risk\RuleSeverity;
use App\Domain\Risk\VerificationContext;

/**
 * R5 — VAT is charged on the invoice but the supplier's régime is not "réel".
 * VAT is deductible only for suppliers under the régime du réel (art. 143);
 * VAT billed by a non-réel supplier is not deductible.
 */
final class RegimeMismatchRule implements RiskRule
{
    public function __construct(private ExposureCalculator $calc) {}

    public function evaluate(VerificationContext $context): ?RuleFinding
    {
        $registry = $context->registry;

        if ($registry === null || ! $context->vatCharged || $registry->isRegimeReel()) {
            return null;
        }

        $vat = $this->calc->vatLoss($context->invoiceAmountXaf);

        return new RuleFinding(
            code: 'R5',
            title: 'Régime fiscal incompatible avec la TVA',
            article: 'CGI art. 143',
            message: 'La facture porte de la TVA alors que le fournisseur n’est pas au '
                .'régime du réel : cette TVA n’est pas déductible.',
            severity: RuleSeverity::Blocking,
            exposureXaf: $vat + $this->calc->badFaithMajoration($vat),
        );
    }
}
