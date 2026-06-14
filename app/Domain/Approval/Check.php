<?php

declare(strict_types=1);

namespace App\Domain\Approval;

/**
 * One of the four pre-approval checks. Pure and stateless.
 */
interface Check
{
    public function evaluate(ApprovalContext $context): CheckResult;
}
