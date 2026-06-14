<?php

declare(strict_types=1);

namespace App\Domain\Approval\Checks;

use App\Domain\Approval\ApprovalContext;
use App\Domain\Approval\Check;
use App\Domain\Approval\CheckKey;
use App\Domain\Approval\CheckResult;

/**
 * Check 3 — the NIU exists in the national taxpayer file. A NIU absent from the
 * file is likely fictitious (CGI art. L 100): fraudulent use is fined per operation.
 */
final class NiuExistenceCheck implements Check
{
    public function evaluate(ApprovalContext $context): CheckResult
    {
        if ($context->niu === null) {
            return CheckResult::fail(
                CheckKey::NiuExistence,
                'NIU absent ou de format invalide — impossible à rechercher.',
            );
        }

        return $context->foundInFile
            ? CheckResult::pass(
                CheckKey::NiuExistence,
                'NIU présent au fichier national des contribuables.',
            )
            : CheckResult::fail(
                CheckKey::NiuExistence,
                'NIU introuvable au fichier : probablement fictif. Refusez la transaction.',
            );
    }
}
