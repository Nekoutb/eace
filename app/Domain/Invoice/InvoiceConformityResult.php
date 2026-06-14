<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

/**
 * The full conditions de forme et de fond outcome for an invoice: the per-check
 * findings and whether the invoice is conform (strict — all checks pass).
 */
final readonly class InvoiceConformityResult
{
    /**
     * @param  array<int, ConformityFinding>  $findings
     */
    public function __construct(
        public bool $conform,
        public array $findings,
    ) {}

    /**
     * @return array<int, ConformityFinding>
     */
    public function failed(): array
    {
        return array_values(array_filter($this->findings, fn (ConformityFinding $f) => ! $f->passed));
    }

    /**
     * @return array<int, ConformityFinding>
     */
    public function ofType(ConformityType $type): array
    {
        return array_values(array_filter($this->findings, fn (ConformityFinding $f) => $f->type === $type));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (ConformityFinding $f) => $f->toArray(), $this->findings);
    }
}
