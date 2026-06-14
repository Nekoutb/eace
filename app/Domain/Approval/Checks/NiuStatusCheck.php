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
                    'NIU actif et inscrit au fichier des contribuables actifs.',
                )
                : CheckResult::fail(
                    CheckKey::NiuStatus,
                    'NIU immatriculé mais absent du fichier des contribuables actifs : TVA non déductible (art. 143 (1)(b)).',
                ),
            FiscalisStatus::Suspendu => CheckResult::fail(
                CheckKey::NiuStatus,
                'NIU suspendu : aucun nouveau paiement tant qu’il n’est pas réactivé.',
            ),
            FiscalisStatus::Radie => CheckResult::fail(
                CheckKey::NiuStatus,
                'NIU radié : toute facture émise après la radiation est nulle fiscalement.',
            ),
            default => CheckResult::fail(
                CheckKey::NiuStatus,
                'Statut du NIU indéterminé (NIU introuvable au fichier).',
            ),
        };
    }
}
