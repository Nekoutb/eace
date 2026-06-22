<?php

declare(strict_types=1);

namespace App\Services\Registry;

use OpenSpout\Reader\XLSX\Reader;

/**
 * Streams a downloaded DGI taxpayer file once (constant memory) to compute the
 * file-level data-quality metrics for the integrity report: schema (expected
 * columns present), completeness (NIU + name fill rates), validity (NIU format)
 * and provenance (SHA-256 + size). Uniqueness/volume are derived by the caller
 * from the database after ingest. Read-only — it never writes.
 */
final class TaxpayerFileIntegrityChecker
{
    /** Expected header names in the DGI file. */
    private const EXPECTED = ['NIU', 'RAISON_SOCIALE', 'SIGLE', 'ACTIVITE_PRINCIPALE', 'REGIME', 'CRI', 'CENTRE_DE_RATTACHEMENT'];

    /** Canonical Cameroon NIU: a letter, 12 digits, a letter (e.g. M010000009392X). */
    private const NIU_PATTERN = '/^[A-Za-z][0-9]{12}[A-Za-z]$/';

    /**
     * @return array{total: int, rowsWithNiu: int, niuValid: int, rowsWithName: int, columnsFound: list<string>, columnsMissing: list<string>, fileHash: ?string, fileSizeBytes: ?int}
     */
    public function check(string $path): array
    {
        $hash = hash_file('sha256', $path);
        $size = @filesize($path);

        $reader = new Reader;
        $reader->open($path);

        $total = 0;
        $withNiu = 0;
        $niuValid = 0;
        $withName = 0;
        $found = [];
        $niuIdx = null;
        $nameIdx = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            $header = true;

            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();

                if ($header) {
                    $header = false;
                    $map = $this->resolve($cells);
                    $found = array_values(array_intersect(self::EXPECTED, array_keys($map)));
                    $niuIdx = $map['NIU'] ?? null;
                    $nameIdx = $map['RAISON_SOCIALE'] ?? null;

                    continue;
                }

                $total++;

                $niu = $this->cell($cells, $niuIdx);
                if ($niu !== null) {
                    $withNiu++;
                    if (preg_match(self::NIU_PATTERN, $niu) === 1) {
                        $niuValid++;
                    }
                }

                if ($this->cell($cells, $nameIdx) !== null) {
                    $withName++;
                }
            }

            break; // first sheet only
        }

        $reader->close();

        return [
            'total' => $total,
            'rowsWithNiu' => $withNiu,
            'niuValid' => $niuValid,
            'rowsWithName' => $withName,
            'columnsFound' => $found,
            'columnsMissing' => array_values(array_diff(self::EXPECTED, $found)),
            'fileHash' => $hash === false ? null : $hash,
            'fileSizeBytes' => $size === false ? null : $size,
        ];
    }

    /**
     * @param  array<int, mixed>  $headerCells
     * @return array<string, int> expected-header => cell index
     */
    private function resolve(array $headerCells): array
    {
        $byKey = [];
        foreach ($headerCells as $index => $name) {
            $key = $this->headerKey($name);
            if ($key !== '') {
                $byKey[$key] = $index;
            }
        }

        $out = [];
        foreach (self::EXPECTED as $header) {
            $key = $this->headerKey($header);
            if (isset($byKey[$key])) {
                $out[$header] = $byKey[$key];
            }
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function cell(array $cells, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        $value = $cells[$index] ?? null;
        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function headerKey(mixed $name): string
    {
        if (! is_scalar($name)) {
            return '';
        }

        $key = mb_strtoupper(trim((string) $name));
        $key = (string) preg_replace('/\s+/u', '_', $key);

        return (string) preg_replace('/[^A-Z0-9_]/u', '', $key);
    }
}
