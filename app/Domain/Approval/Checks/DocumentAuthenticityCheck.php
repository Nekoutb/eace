<?php

declare(strict_types=1);

namespace App\Domain\Approval\Checks;

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Approval\ApprovalContext;
use App\Domain\Approval\Check;
use App\Domain\Approval\CheckKey;
use App\Domain\Approval\CheckResult;

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
                'Document confirmé authentique auprès du système Harmony2.',
            ),
            AcfAuthenticityStatus::NotAuthentic => CheckResult::fail(
                CheckKey::DocumentAuthenticity,
                'Document non authentique : absent de la liste publiée par la DGI.',
            ),
            default => CheckResult::fail(
                CheckKey::DocumentAuthenticity,
                'Authenticité non vérifiée : aucune ACF authentifiée n’est rattachée.',
            ),
        };
    }
}
