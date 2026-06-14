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
 * R3 — the NIU is found but suspended or struck off. A radié NIU makes any
 * invoice issued after radiation fiscally void; a suspendu NIU should stop new
 * payments. Either way the VAT/charge is at risk (art. 143).
 */
final class SuspendedOrRevokedRule implements RiskRule
{
    public function __construct(private ExposureCalculator $calc) {}

    public function evaluate(VerificationContext $context): ?RuleFinding
    {
        $registry = $context->registry;

        if ($registry === null || $registry->status === FiscalisStatus::Actif) {
            return null;
        }

        $isRevoked = $registry->status === FiscalisStatus::Radie;

        return new RuleFinding(
            code: 'R3',
            title: (string) __($isRevoked ? 'rules.r3.title_revoked' : 'rules.r3.title_suspended'),
            article: 'CGI art. 143',
            message: (string) __($isRevoked ? 'rules.r3.message_revoked' : 'rules.r3.message_suspended'),
            severity: RuleSeverity::Blocking,
            exposureXaf: $this->calc->rejectedInvoiceExposure($context->invoiceAmountXaf),
        );
    }
}
