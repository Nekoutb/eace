<?php

declare(strict_types=1);

namespace App\Domain\Approval\Checks;

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Approval\ApprovalContext;
use App\Domain\Approval\Check;
use App\Domain\Approval\CheckKey;
use App\Domain\Approval\CheckResult;
use App\Domain\Approval\VerificationStage;

/**
 * Check 1 — the ACF is a genuine DGI document (Harmony2, CGI art. L 94 bis al. 3).
 * Passes only when authenticity is confirmed; unverified or absent fails.
 */
final class DocumentAuthenticityCheck implements Check
{
    public function evaluate(ApprovalContext $context): CheckResult
    {
        return match ($context->acfAuthenticity) {
            AcfAuthenticityStatus::Authentic => CheckResult::pass(
                CheckKey::DocumentAuthenticity,
                (string) __('app.check.msg.auth_authentic'),
            ),
            AcfAuthenticityStatus::NotAuthentic => CheckResult::fail(
                CheckKey::DocumentAuthenticity,
                (string) __('app.check.msg.auth_not_authentic'),
            ),
            // With no certificate in the register at settlement there is no
            // document to authenticate — the check cannot run (distinct from a
            // certificate that exists but is unverified or fake).
            default => $context->stage === VerificationStage::Settlement && $context->acfStatus === null
                ? CheckResult::notPerformed(
                    CheckKey::DocumentAuthenticity,
                    (string) __('app.check.msg.validity_absent_settlement'),
                )
                : CheckResult::fail(
                    CheckKey::DocumentAuthenticity,
                    (string) __('app.check.msg.auth_unverified'),
                ),
        };
    }
}
