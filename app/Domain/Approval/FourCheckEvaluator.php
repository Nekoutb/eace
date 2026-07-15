<?php

declare(strict_types=1);

namespace App\Domain\Approval;

use App\Domain\Approval\Checks\AcfValidityCheck;
use App\Domain\Approval\Checks\DocumentAuthenticityCheck;
use App\Domain\Approval\Checks\NiuExistenceCheck;
use App\Domain\Approval\Checks\NiuStatusCheck;

/**
 * Runs the four checks and applies the STRICT rule: the invoice is accepted only
 * when every check passes; any single failure rejects it.
 */
final class FourCheckEvaluator
{
    /**
     * @param  array<int, Check>  $checks
     */
    public function __construct(private array $checks) {}

    public static function default(): self
    {
        return new self([
            new DocumentAuthenticityCheck,
            new NiuStatusCheck,
            new NiuExistenceCheck,
            new AcfValidityCheck,
        ]);
    }

    public function evaluate(ApprovalContext $context): ApprovalResult
    {
        $results = array_map(fn (Check $check) => $check->evaluate($context), $this->checks);

        $allPassed = array_reduce($results, fn (bool $carry, CheckResult $r) => $carry && $r->passed === true, true);

        return new ApprovalResult(
            decision: $allPassed ? ApprovalDecision::Accepted : ApprovalDecision::Rejected,
            checks: $results,
        );
    }
}
