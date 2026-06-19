<?php

declare(strict_types=1);

namespace App\Services\Registry;

use App\Models\TaxpayerFileExtraction;
use App\Models\TaxpayerFileRecord;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

/**
 * Streams a DGI taxpayer file (FICHIER … .xlsx) into the central
 * taxpayer_file_records table. OpenSpout reads row-by-row (constant memory, the
 * file is ~40 MB / hundreds of thousands of rows), upserting in chunks. Each run
 * is a full snapshot: rows not in the new file are pruned, and an
 * TaxpayerFileExtraction row records the result for audit.
 *
 * Expected columns: N°, RAISON_SOCIALE, SIGLE, NIU, ACTIVITE_PRINCIPALE, REGIME,
 * CRI, CENTRE_DE_RATTACHEMENT.
 */
final class TaxpayerFileIngestionService
{
    private const CHUNK = 1000;

    /**
     * @param  array<string, mixed>  $meta  source_url, file_path, file_hash
     */
    public function ingest(string $absolutePath, string $periodLabel, array $meta = []): TaxpayerFileExtraction
    {
        $extraction = TaxpayerFileExtraction::query()->create([
            'period_label' => $periodLabel,
            'source_url' => $meta['source_url'] ?? null,
            'file_path' => $meta['file_path'] ?? null,
            'file_hash' => $meta['file_hash'] ?? null,
            'status' => 'pending',
        ]);

        // The last good snapshot — used as a sanity floor so a truncated or
        // corrupt download can never wipe out a healthy register (see below).
        $previousRowCount = (int) TaxpayerFileExtraction::query()
            ->where('status', 'completed')
            ->whereKeyNot($extraction->getKey())
            ->orderByDesc('id')
            ->value('row_count');

        $central = (string) config('tenancy.database.central_connection');

        try {
            // One transaction for the whole snapshot: hundreds of thousands of
            // upserts + the prune commit with a single fsync (minutes, not hours
            // under WAL) and are atomic — readers keep seeing the old register
            // until the new one is fully in place, and any failure rolls back
            // cleanly, leaving the previous snapshot untouched.
            $count = DB::connection($central)->transaction(function () use ($absolutePath, $extraction, $previousRowCount): int {
                $count = $this->stream($absolutePath, (int) $extraction->id);

                $this->assertNotCollapsed($count, $previousRowCount);

                // Prune NIUs that dropped off the new file (a full monthly snapshot).
                TaxpayerFileRecord::query()
                    ->where('taxpayer_file_extraction_id', '!=', $extraction->id)
                    ->delete();

                return $count;
            });

            $extraction->update([
                'row_count' => $count,
                'status' => 'completed',
                'extracted_at' => now(),
            ]);
        } catch (Throwable $e) {
            $extraction->update(['status' => 'failed', 'message' => $e->getMessage()]);

            throw $e;
        }

        return $extraction;
    }

    /**
     * Refuse a snapshot whose row count has collapsed against the last good one
     * (default: fewer than half). A healthy month-to-month register barely moves;
     * a sudden collapse means a truncated or corrupt download, and replacing the
     * live register with it would silently break every NIU control.
     */
    private function assertNotCollapsed(int $count, int $previousRowCount): void
    {
        if ($previousRowCount <= 0) {
            return; // First ever import — nothing to compare against.
        }

        $ratio = (float) config('niu.taxpayer_file.min_row_ratio', 0.5);
        $floor = (int) floor($previousRowCount * $ratio);

        if ($count < $floor) {
            throw new RuntimeException(
                "Taxpayer file rejected: {$count} rows is below the safety floor of {$floor} ".
                "({$ratio} of the previous {$previousRowCount}). The download looks truncated or corrupt; ".
                'the existing register was left untouched.'
            );
        }
    }

    private function stream(string $absolutePath, int $extractionId): int
    {
        $reader = new Reader;
        $reader->open($absolutePath);

        $count = 0;
        $buffer = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            /** @var array<string, int> $columns header name => column index */
            $columns = [];
            $header = true;

            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();

                if ($header) {
                    $header = false;
                    foreach ($cells as $index => $name) {
                        $key = $this->headerKey($name);
                        if ($key !== '') {
                            $columns[$key] = $index;
                        }
                    }

                    continue;
                }

                $niu = $this->cell($cells, $columns, 'NIU');
                if ($niu === null) {
                    continue;
                }

                $buffer[] = [
                    'niu' => $niu,
                    'raison_sociale' => $this->cell($cells, $columns, 'RAISON_SOCIALE'),
                    'sigle' => $this->cell($cells, $columns, 'SIGLE'),
                    'activite' => $this->cell($cells, $columns, 'ACTIVITE_PRINCIPALE'),
                    'regime' => $this->cell($cells, $columns, 'REGIME'),
                    'cri' => $this->cell($cells, $columns, 'CRI'),
                    'centre' => $this->cell($cells, $columns, 'CENTRE_DE_RATTACHEMENT'),
                    'taxpayer_file_extraction_id' => $extractionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $count++;

                if (count($buffer) >= self::CHUNK) {
                    $this->flush($buffer);
                    $buffer = [];
                }
            }

            break; // first sheet only
        }

        if ($buffer !== []) {
            $this->flush($buffer);
        }

        $reader->close();

        return $count;
    }

    /**
     * Read a cell by HEADER NAME, not fixed index — the monthly archive (8 cols)
     * and the live "Exporter" list (12 cols, with TELEPHONE/VILLE/…) order their
     * columns differently.
     *
     * @param  array<int, mixed>  $cells
     * @param  array<string, int>  $columns
     */
    private function cell(array $cells, array $columns, string $header): ?string
    {
        $index = $columns[$this->headerKey($header)] ?? null;

        return $index !== null ? $this->str($cells[$index] ?? null) : null;
    }

    /** Normalise a header to A–Z/0–9/underscore for resilient matching. */
    private function headerKey(mixed $name): string
    {
        if (! is_scalar($name)) {
            return '';
        }

        $key = mb_strtoupper(trim((string) $name));
        $key = (string) preg_replace('/\s+/u', '_', $key);

        return (string) preg_replace('/[^A-Z0-9_]/u', '', $key);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function flush(array $rows): void
    {
        TaxpayerFileRecord::query()->upsert(
            $rows,
            ['niu'],
            ['raison_sociale', 'sigle', 'activite', 'regime', 'cri', 'centre', 'taxpayer_file_extraction_id', 'updated_at'],
        );
    }

    private function str(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
