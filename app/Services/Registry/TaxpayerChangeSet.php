<?php

declare(strict_types=1);

namespace App\Services\Registry;

/**
 * What changed between the previous register snapshot and the new one: the NIUs
 * that dropped off, and the NIUs that were added — split into genuinely first-time
 * (never seen in any earlier file) and returning (seen before, had dropped off).
 *
 * The `added`/`removed` arrays hold the NIUs themselves (capped) for the CSV
 * attachment; the totals are the true counts even when the lists are capped.
 */
final readonly class TaxpayerChangeSet
{
    /**
     * @param  list<string>  $added  added NIUs (capped at CAP)
     * @param  list<string>  $removed  removed NIUs (capped at CAP)
     * @param  list<string>  $returning  subset of $added that are returning (for per-NIU labelling)
     */
    public function __construct(
        public int $addedTotal = 0,
        public int $addedFirstTime = 0,
        public int $addedReturning = 0,
        public int $removedTotal = 0,
        public array $added = [],
        public array $removed = [],
        public array $returning = [],
        public bool $capped = false,
    ) {}

    public function hasChanges(): bool
    {
        return $this->addedTotal > 0 || $this->removedTotal > 0;
    }

    /**
     * niu => true for the added NIUs that are returning (seen before), for labelling.
     *
     * @return array<string, bool>
     */
    public function returningLookup(): array
    {
        return array_fill_keys($this->returning, true);
    }
}
