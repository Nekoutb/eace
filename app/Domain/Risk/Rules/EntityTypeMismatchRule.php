<?php

declare(strict_types=1);

namespace App\Domain\Risk\Rules;

use App\Domain\Risk\ExposureCalculator;
use App\Domain\Risk\RiskRule;
use App\Domain\Risk\RuleFinding;
use App\Domain\Risk\RuleSeverity;
use App\Domain\Risk\VerificationContext;

/**
 * R6 — the NIU's P/M prefix does not match the expected entity type. A SARL
 * presenting a NIU that starts with P is a major red flag: the NIU is probably
 * the manager's personal one, not the company's (article, astuce n°2).
 */
final class EntityTypeMismatchRule implements RiskRule
{
    public function __construct(private ExposureCalculator $calc) {}

    public function evaluate(VerificationContext $context): ?RuleFinding
    {
        $niu = $context->niu;
        $expected = $context->expectedEntityType;

        if ($niu === null || $expected === null || $niu->entityType() === $expected) {
            return null;
        }

        $exposure = $this->calc->vatLoss($context->invoiceAmountXaf)
            + $this->calc->invoiceFine($context->invoiceAmountXaf);

        return new RuleFinding(
            code: 'R6',
            title: (string) __('rules.r6.title'),
            article: 'CGI art. L 102',
            message: (string) __('rules.r6.message'),
            severity: RuleSeverity::Blocking,
            exposureXaf: $exposure,
        );
    }
}
