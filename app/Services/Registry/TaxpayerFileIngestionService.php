<?php

declare(strict_types=1);

namespace App\Services\Registry;

use App\Models\TaxpayerFileExtraction;
use App\Models\TaxpayerFileRecord;
use OpenSpout\Reader\XLSX\Reader;
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

        try {
            $count = $this->stream($absolutePath, (int) $extraction->id);

            // Prune NIUs that dropped off the new file (a full monthly snapshot).
            TaxpayerFileRecord::query()
                ->where('taxpayer_file_extraction_id', '!=', $extraction->id)
                ->delete();

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

    private function stream(string $absolutePath, int $extractionId): int
    {
        $reader = new Reader;
        $reader->open($absolutePath);

        $count = 0;
        $buffer = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $first = true;
            foreach ($sheet->getRowIterator() as $row) {
                if ($first) {
                    $first = false; // header

                    continue;
                }

                $cells = $row->toArray();
                $niu = $this->str($cells[3] ?? null);

                if ($niu === null) {
                    continue;
                }

                $buffer[] = [
                    'niu' => $niu,
                    'raison_sociale' => $this->str($cells[1] ?? null),
                    'sigle' => $this->str($cells[2] ?? null),
                    'activite' => $this->str($cells[4] ?? null),
                    'regime' => $this->str($cells[5] ?? null),
                    'cri' => $this->str($cells[6] ?? null),
                    'centre' => $this->str($cells[7] ?? null),
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
