<?php

declare(strict_types=1);

namespace App\Domain\Risk;

use App\Domain\Niu\Verdict;

/**
 * The outcome of running the rule set over a context: the headline Verdict, the
 * findings that fired (sorted strongest first), and the total estimated FCFA
 * exposure across them.
 */
final readonly class EngineResult
{
    /**
     * @param  array<int, RuleFinding>  $findings
     */
    public function __construct(
        public Verdict $verdict,
        public array $findings,
        public int $totalExposureXaf,
    ) {}

    public function hasFindings(): bool
    {
        return $this->findings !== [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findingsToArray(): array
    {
        return array_map(fn (RuleFinding $f) => $f->toArray(), $this->findings);
    }
}
