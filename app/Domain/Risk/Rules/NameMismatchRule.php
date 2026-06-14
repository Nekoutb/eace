<?php

declare(strict_types=1);

namespace App\Domain\Risk\Rules;

use App\Domain\Risk\ExposureCalculator;
use App\Domain\Risk\NameMatcher;
use App\Domain\Risk\RiskRule;
use App\Domain\Risk\RuleFinding;
use App\Domain\Risk\RuleSeverity;
use App\Domain\Risk\VerificationContext;

/**
 * R7 — the name on the invoice does not match the raison sociale registered for
 * the NIU. A discordant invoice is erroneous and exposes a fine of 100 % of the
 * transaction (art. L 102).
 */
final class NameMismatchRule implements RiskRule
{
    public function __construct(private ExposureCalculator $calc) {}

    public function evaluate(VerificationContext $context): ?RuleFinding
    {
        $registry = $context->registry;
        $invoiceName = $context->invoiceName;

        if ($registry === null
            || $registry->taxpayerName === null
            || $invoiceName === null
            || NameMatcher::matches($registry->taxpayerName, $invoiceName)) {
            return null;
        }

        return new RuleFinding(
            code: 'R7',
            title: (string) __('rules.r7.title'),
            article: 'CGI art. L 102',
            message: (string) __('rules.r7.message'),
            severity: RuleSeverity::Warning,
            exposureXaf: $this->calc->invoiceFine($context->invoiceAmountXaf),
        );
    }
}
