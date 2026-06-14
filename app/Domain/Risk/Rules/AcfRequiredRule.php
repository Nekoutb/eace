<?php

declare(strict_types=1);

namespace App\Domain\Risk\Rules;

use App\Domain\Risk\RiskRule;
use App\Domain\Risk\RuleFinding;
use App\Domain\Risk\RuleSeverity;
use App\Domain\Risk\VerificationContext;

/**
 * R10 — a public-sector settlement, export, or foreign transfer without a valid
 * ACF. An Attestation de Conformité Fiscale in force is required for these
 * operations (art. L 94 ter / quater); its absence makes the request
 * inadmissible.
 */
final class AcfRequiredRule implements RiskRule
{
    public function evaluate(VerificationContext $context): ?RuleFinding
    {
        if (! $context->publicSettlementOrExport || $context->hasValidAcf) {
            return null;
        }

        return new RuleFinding(
            code: 'R10',
            title: (string) __('rules.r10.title'),
            article: 'CGI art. L 94 ter / quater',
            message: (string) __('rules.r10.message'),
            severity: RuleSeverity::Blocking,
            exposureXaf: 0,
        );
    }
}
