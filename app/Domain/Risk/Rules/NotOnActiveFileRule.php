<?php

declare(strict_types=1);

namespace App\Domain\Risk\Rules;

use App\Domain\Niu\FiscalisStatus;
use App\Domain\Risk\ExposureCalculator;
use App\Domain\Risk\RiskRule;
use App\Domain\Risk\RuleFinding;
use App\Domain\Risk\RuleSeverity;
use App\Domain\Risk\VerificationContext;

/**
 * R4 — the NIU is active but not on the "fichier des contribuables actifs".
 * VAT is deductible only if the supplier is on the active file at the moment of
 * invoicing (art. 143 (1)(b)); otherwise the deduction is refused.
 */
final class NotOnActiveFileRule implements RiskRule
{
    public function __construct(private ExposureCalculator $calc) {}

    public function evaluate(VerificationContext $context): ?RuleFinding
    {
        $registry = $context->registry;

        if ($registry === null
            || $registry->status !== FiscalisStatus::Actif
            || $registry->onActiveFile) {
            return null;
        }

        $vat = $this->calc->vatLoss($context->invoiceAmountXaf);

        return new RuleFinding(
            code: 'R4',
            title: 'Hors fichier des contribuables actifs',
            article: 'CGI art. 143 (1)(b)',
            message: 'Le contribuable est immatriculé mais absent du fichier des '
                .'contribuables actifs : la TVA d’amont n’ouvre pas droit à déduction.',
            severity: RuleSeverity::Blocking,
            exposureXaf: $vat + $this->calc->badFaithMajoration($vat),
        );
    }
}
