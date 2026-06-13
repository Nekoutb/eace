<?php

declare(strict_types=1);

namespace App\Domain\Risk;

/**
 * A single risk rule. Returns a RuleFinding when it fires against the given
 * context, or null when it does not apply. Each rule is pure and stateless, so
 * the rule set is just an ordered list the engine runs.
 */
interface RiskRule
{
    public function evaluate(VerificationContext $context): ?RuleFinding;
}
