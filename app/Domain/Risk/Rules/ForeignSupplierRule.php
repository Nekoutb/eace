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
            title: (string) __('rules.r11.title'),
            article: 'CGI (TSR 15 %, autoliquidation TVA)',
            message: (string) __('rules.r11.message'),
            severity: RuleSeverity::Warning,
            exposureXaf: $tsr,
        );
    }
}
