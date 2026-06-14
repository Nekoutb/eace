<?php

declare(strict_types=1);

namespace App\Domain\Approval;

/**
 * The outcome of the four-check evaluation: the strict decision and the four
 * individual results behind it.
 */
final readonly class ApprovalResult
{
    /**
     * @param  array<int, CheckResult>  $checks
     */
    public function __construct(
        public ApprovalDecision $decision,
        public array $checks,
    ) {}

    public function accepted(): bool
    {
        return $this->decision === ApprovalDecision::Accepted;
    }

    /**
     * @return array<int, CheckResult>
     */
    public function failedChecks(): array
    {
        return array_values(array_filter($this->checks, fn (CheckResult $c) => ! $c->passed));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function checksToArray(): array
    {
        return array_map(fn (CheckResult $c) => $c->toArray(), $this->checks);
    }
}
