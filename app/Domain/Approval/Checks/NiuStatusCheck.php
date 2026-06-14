<?php

declare(strict_types=1);

namespace App\Domain\Approval\Checks;

use App\Domain\Approval\ApprovalContext;
use App\Domain\Approval\Check;
use App\Domain\Approval\CheckKey;
use App\Domain\Approval\CheckResult;
use App\Domain\Niu\FiscalisStatus;

/**
 * Check 2 — the NIU is active AND on the fichier des contribuables actifs
 * (CGI art. 143 (1)(b): deductibility requires the supplier be "inscrit sur le
 * fichier du contribuable actif au moment de la facturation"). A suspended or
 * struck-off NIU fails; an active NIU that has dropped off the active file fails.
 */
final class NiuStatusCheck implements Check
{
    public function evaluate(ApprovalContext $context): CheckResult
    {
        return match ($context->fiscalisStatus) {
            FiscalisStatus::Actif => $context->onActiveFile
                ? CheckResult::pass(
                    CheckKey::NiuStatus,
                    (string) __('app.check.msg.status_actif_on_file'),
                )
                : CheckResult::fail(
                    CheckKey::NiuStatus,
                    (string) __('app.check.msg.status_actif_off_file'),
                ),
            FiscalisStatus::Suspendu => CheckResult::fail(
                CheckKey::NiuStatus,
                (string) __('app.check.msg.status_suspendu'),
            ),
            FiscalisStatus::Radie => CheckResult::fail(
                CheckKey::NiuStatus,
                (string) __('app.check.msg.status_radie'),
            ),
            default => CheckResult::fail(
                CheckKey::NiuStatus,
                (string) __('app.check.msg.status_unknown'),
            ),
        };
    }
}
