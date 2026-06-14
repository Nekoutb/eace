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
            title: (string) __('rules.r9.title'),
            article: 'CGI art. 143 (1)(d)',
            message: (string) __('rules.r9.message'),
            severity: RuleSeverity::Blocking,
            exposureXaf: $exposure,
        );
    }
}
