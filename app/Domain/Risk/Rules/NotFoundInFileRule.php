<?php

declare(strict_types=1);

namespace App\Domain\Risk\Rules;

use App\Domain\Risk\ExposureCalculator;
use App\Domain\Risk\RiskRule;
use App\Domain\Risk\RuleFinding;
use App\Domain\Risk\RuleSeverity;
use App\Domain\Risk\VerificationContext;

/**
 * R2 — the NIU is well-formed but absent from the Fiscalis file: a likely
 * phantom or fabricated NIU. Fraudulent use is fined 1 000 000 F per operation
 * (art. L 100 (3)), and the VAT is not deductible.
 */
final class NotFoundInFileRule implements RiskRule
{
    public function __construct(private ExposureCalculator $calc) {}

    public function evaluate(VerificationContext $context): ?RuleFinding
    {
        if (! $context->hasValidNiu() || $context->foundInFile()) {
            return null;
        }

        $exposure = $this->calc->niuFraudFine()
            + $this->calc->vatLoss($context->invoiceAmountXaf);

        return new RuleFinding(
            code: 'R2',
            title: (string) __('rules.r2.title'),
            article: 'CGI art. L 100 (3)',
            message: (string) __('rules.r2.message'),
            severity: RuleSeverity::Blocking,
            exposureXaf: $exposure,
        );
    }
}
