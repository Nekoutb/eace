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
 * Passes only when authenticity is confirmed. The failure message is specific
 * about the cause and stays consistent with the validity check (check 4), which
 * reads the same certificate: NOT authentic (Harmony2 rejected it), could not be
 * confirmed (portal unreachable / pending — but the ACF IS attached), or absent
 * (no certificate — same wording as the validity check).
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
            // Unverified authenticity. Be specific about WHY, and never
            // contradict the validity check (which reports on the same
            // certificate): a present ACF must never be described as "absent".
            default => match (true) {
                // A certificate IS attached (check 4 reports on its dates) but
                // Harmony2 could not confirm it — portal unreachable or verdict
                // pending. Do NOT say "absent" / "attach an ACF": it is present.
                $context->acfStatus !== null => CheckResult::fail(
                    CheckKey::DocumentAuthenticity,
                    (string) __('app.check.msg.auth_unconfirmed'),
                ),
                // No certificate at all — identical wording to the validity
                // check's absent handling, so the two never contradict: neutral
                // "not performed" at settlement, plain "attach it" at recording.
                $context->stage === VerificationStage::Settlement => CheckResult::notPerformed(
                    CheckKey::DocumentAuthenticity,
                    (string) __('app.check.msg.validity_absent_settlement'),
                ),
                default => CheckResult::fail(
                    CheckKey::DocumentAuthenticity,
                    (string) __('app.check.msg.validity_none'),
                ),
            },
        };
    }
}
