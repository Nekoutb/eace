<?php

declare(strict_types=1);

namespace App\Domain\Approval\Checks;

use App\Domain\Acf\AcfStatus;
use App\Domain\Approval\ApprovalContext;
use App\Domain\Approval\Check;
use App\Domain\Approval\CheckKey;
use App\Domain\Approval\CheckResult;
use App\Domain\Approval\VerificationStage;

/**
 * Check 4 — the ACF is in force (CGI art. L 94 bis, 3-month validity).
 *
 * Stage-aware (WORKFLOW.md, confirmed decision #2): at RECORDING the certificate
 * was just scanned and only needs to be in force today, so "bientôt expirée"
 * still passes. At SETTLEMENT the ACF is being reused, so it must be cleanly
 * valid with the full buffer remaining — an ACF inside the buffer is blocked,
 * which is what stops a payment on a soon-to-expire clearance certificate.
 */
final class AcfValidityCheck implements Check
{
    public function evaluate(ApprovalContext $context): CheckResult
    {
        return match ($context->acfStatus) {
            AcfStatus::Valid => CheckResult::pass(
                CheckKey::AcfValidity,
                (string) __('app.check.msg.validity_valid'),
            ),
            AcfStatus::ExpiringSoon => $context->stage === VerificationStage::Recording
                ? CheckResult::pass(
                    CheckKey::AcfValidity,
                    (string) __('app.check.msg.validity_expiring_recording'),
                )
                : CheckResult::fail(
                    CheckKey::AcfValidity,
                    (string) __('app.check.msg.validity_expiring_settlement'),
                ),
            AcfStatus::Expired => CheckResult::fail(
                CheckKey::AcfValidity,
                (string) __('app.check.msg.validity_expired'),
            ),
            AcfStatus::NotAuthentic => CheckResult::fail(
                CheckKey::AcfValidity,
                (string) __('app.check.msg.validity_not_authentic'),
            ),
            default => CheckResult::fail(
                CheckKey::AcfValidity,
                (string) __('app.check.msg.validity_none'),
            ),
        };
    }
}
