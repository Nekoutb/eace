<?php

declare(strict_types=1);

namespace App\Services\Registry;

/**
 * The data-integrity report for one taxpayer-file refresh, emailed after each run.
 *
 * It covers the recognised data-quality dimensions for a tabular dataset:
 *  - Provenance / integrity: SHA-256 file hash + size (the file we ingested is
 *    exactly the one downloaded; the hash detects any corruption or tampering).
 *  - Structure: the expected columns were present (matched by header name).
 *  - Completeness: the share of rows carrying the key fields (NIU, name).
 *  - Validity: the share of NIUs conforming to the canonical format.
 *  - Uniqueness: duplicate NIUs in the file (collapsed on import).
 *  - Volume / anomaly: row count and the change vs the previous snapshot, plus the
 *    safety floor that rejects a collapsed download.
 */
final readonly class TaxpayerIntegrityReport
{
    /**
     * @param  list<string>  $columnsFound
     * @param  list<string>  $columnsMissing
     */
    public function __construct(
        public string $status,            // completed | unchanged | failed
        public string $period,
        public ?string $fileHash = null,
        public ?int $fileSizeBytes = null,
        public int $totalRows = 0,
        public int $rowsWithNiu = 0,
        public int $distinctNiu = 0,
        public int $rowsWithName = 0,
        public int $niuFormatValid = 0,
        public array $columnsFound = [],
        public array $columnsMissing = [],
        public ?int $previousRowCount = null,
        public ?string $error = null,
    ) {}

    public function duplicateNiu(): int
    {
        return max(0, $this->rowsWithNiu - $this->distinctNiu);
    }

    public function rowsMissingNiu(): int
    {
        return max(0, $this->totalRows - $this->rowsWithNiu);
    }

    public function delta(): ?int
    {
        return $this->previousRowCount !== null ? $this->distinctNiu - $this->previousRowCount : null;
    }

    public function niuCompleteness(): float
    {
        return $this->pct($this->rowsWithNiu, $this->totalRows);
    }

    public function nameCompleteness(): float
    {
        return $this->pct($this->rowsWithName, $this->totalRows);
    }

    public function niuValidity(): float
    {
        return $this->pct($this->niuFormatValid, $this->rowsWithNiu);
    }

    private function pct(int $n, int $of): float
    {
        return $of > 0 ? round($n / $of * 100, 2) : 0.0;
    }

    /** Whether every critical check passed (status ok + all expected columns present). */
    public function ok(): bool
    {
        return $this->status !== 'failed' && $this->columnsMissing === [];
    }

    /**
     * Non-fatal findings worth flagging in the email.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];

        if ($this->columnsMissing !== []) {
            $warnings[] = 'Missing expected columns: '.implode(', ', $this->columnsMissing);
        }

        if ($this->totalRows > 0 && $this->niuCompleteness() < 95.0) {
            $warnings[] = 'Only '.$this->niuCompleteness().'% of rows carry a NIU.';
        }

        if ($this->rowsWithNiu > 0 && $this->niuValidity() < 90.0) {
            $warnings[] = 'Only '.$this->niuValidity().'% of NIUs match the canonical format.';
        }

        if ($this->duplicateNiu() > 0) {
            $warnings[] = $this->duplicateNiu().' duplicate NIU(s) in the file (collapsed on import).';
        }

        $delta = $this->delta();
        if ($delta !== null && $this->previousRowCount > 0 && abs($delta) > (int) ($this->previousRowCount * 0.1)) {
            $warnings[] = 'Row count changed by '.number_format($delta).' vs the previous snapshot (>10%).';
        }

        return $warnings;
    }
}
