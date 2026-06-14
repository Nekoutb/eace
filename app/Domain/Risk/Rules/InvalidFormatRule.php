<?php

declare(strict_types=1);

namespace App\Domain\Risk\Rules;

use App\Domain\Risk\ExposureCalculator;
use App\Domain\Risk\RiskRule;
use App\Domain\Risk\RuleFinding;
use App\Domain\Risk\RuleSeverity;
use App\Domain\Risk\VerificationContext;

/**
 * R1 — the input is not a valid NIU. No NIU on an invoice means the VAT is not
 * deductible (art. L 101) and the invoice is incomplete (art. L 102).
 */
final class InvalidFormatRule implements RiskRule
{
    public function __construct(private ExposureCalculator $calc) {}

    public function evaluate(VerificationContext $context): ?RuleFinding
    {
        if ($context->hasValidNiu()) {
            return null;
        }

        $exposure = $this->calc->vatLoss($context->invoiceAmountXaf)
            + $this->calc->invoiceFine($context->invoiceAmountXaf);

        return new RuleFinding(
            code: 'R1',
            title: (string) __('rules.r1.title'),
            article: 'CGI art. L 101 & L 102',
            message: (string) __('rules.r1.message'),
            severity: RuleSeverity::Blocking,
            exposureXaf: $exposure,
        );
    }
}
