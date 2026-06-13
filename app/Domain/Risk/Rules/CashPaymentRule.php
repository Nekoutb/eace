<?php

declare(strict_types=1);

namespace App\Domain\Risk\Rules;

use App\Domain\Risk\ExposureCalculator;
use App\Domain\Risk\PaymentMethod;
use App\Domain\Risk\RiskRule;
use App\Domain\Risk\RuleFinding;
use App\Domain\Risk\RuleSeverity;
use App\Domain\Risk\VerificationContext;

/**
 * R9 — an operation of at least 100 000 FCFA settled in cash. Such operations
 * do not open the right to VAT deduction (art. 143 (1)(d)).
 */
final class CashPaymentRule implements RiskRule
{
    public function __construct(private ExposureCalculator $calc) {}

    public function evaluate(VerificationContext $context): ?RuleFinding
    {
        if ($context->paymentMethod !== PaymentMethod::Cash
            || ! $this->calc->cashThresholdBreached($context->invoiceAmountXaf)) {
            return null;
        }

        $exposure = $this->calc->vatLoss($context->invoiceAmountXaf)
            + $this->calc->chargeLoss($context->invoiceAmountXaf);

        return new RuleFinding(
            code: 'R9',
            title: 'Paiement en espèces ≥ 100 000 F',
            article: 'CGI art. 143 (1)(d)',
            message: 'Une opération d’au moins 100 000 F payée en espèces n’ouvre pas droit '
                .'à déduction de la TVA. Réglez par voie bancaire.',
            severity: RuleSeverity::Blocking,
            exposureXaf: $exposure,
        );
    }
}
