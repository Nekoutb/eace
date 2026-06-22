<?php

declare(strict_types=1);

namespace App\Services\Registry;

use App\Models\TaxpayerFileExtraction;
use App\Models\TaxpayerFileRecord;
use App\Models\TaxpayerNiuSeen;
use Illuminate\Database\Query\Builder as QueryBuilder;
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

    /** Target column => its header name in the DGI file. */
    private const FIELDS = [
        'niu' => 'NIU',
        'raison_sociale' => 'RAISON_SOCIALE',
        'sigle' => 'SIGLE',
        'activite' => 'ACTIVITE_PRINCIPALE',
        'regime' => 'REGIME',
        'cri' => 'CRI',
        'centre' => 'CENTRE_DE_RATTACHEMENT',
    ];

    private ?TaxpayerChangeSet $lastChangeSet = null;

    /** The added/removed NIU change set computed by the most recent ingest, if any. */
    public function lastChangeSet(): ?TaxpayerChangeSet
    {
        return $this->lastChangeSet;
    }

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
            $count = DB::connection($central)->transaction(function () use ($absolutePath, $extraction, $previousRowCount, $central): int {
                // Seed the "ever seen" NIU history once from the existing register, so
                // a NIU that drops off and later returns is recognised (not mislabelled
                // first-time) from the very first run onward.
                if (TaxpayerNiuSeen::query()->doesntExist()) {
                    $this->seedSeen($central);
                }

                // The upsert keeps existing rows' ids and assigns new ids to inserted
                // (added) rows, so "id beyond this mark" == the NIUs this file added.
                $maxIdBefore = (int) (TaxpayerFileRecord::query()->max('id') ?? 0);

                $count = $this->stream($absolutePath, (int) $extraction->id);

                $this->assertNotCollapsed($count, $previousRowCount);

                // Diff the new snapshot vs the previous one + the ever-seen history,
                // BEFORE pruning dropped NIUs and before recording this run's new ones.
                $this->lastChangeSet = $this->computeChangeSet($central, (int) $extraction->id, $maxIdBefore);

                // Prune NIUs that dropped off the new file (a full snapshot).
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

    /** Seed the ever-seen NIU history from the current register (one-time, when empty). */
    private function seedSeen(string $central): void
    {
        DB::connection($central)->statement(
            'INSERT OR IGNORE INTO taxpayer_niu_seen (niu, first_seen_at) SELECT niu, ? FROM taxpayer_file_records',
            [now()->toDateTimeString()],
        );
    }

    /**
     * Diff the just-loaded snapshot against the previous one and the ever-seen
     * history: which NIUs were added (split first-time vs returning) and removed.
     * Must run AFTER the upsert but BEFORE the prune + before recording new NIUs.
     */
    private function computeChangeSet(string $central, int $extractionId, int $maxIdBefore): TaxpayerChangeSet
    {
        $cap = 50000;

        $addedTotal = TaxpayerFileRecord::query()->where('id', '>', $maxIdBefore)->count();
        $removedTotal = TaxpayerFileRecord::query()->where('taxpayer_file_extraction_id', '!=', $extractionId)->count();

        // Of the added NIUs, which have we EVER seen before (returning)? Computed
        // before this run's new NIUs are recorded, so the history reflects the past.
        $returningCount = 0;
        $returningNius = [];
        if ($addedTotal > 0) {
            $returningBase = fn () => TaxpayerFileRecord::query()
                ->where('id', '>', $maxIdBefore)
                ->whereExists(fn (QueryBuilder $q) => $q->from('taxpayer_niu_seen')->whereColumn('taxpayer_niu_seen.niu', 'taxpayer_file_records.niu'));
            $returningCount = $returningBase()->count();
            $returningNius = $returningBase()->limit($cap)->pluck('niu')->all();
        }

        $added = TaxpayerFileRecord::query()->where('id', '>', $maxIdBefore)->limit($cap)->pluck('niu')->all();
        $removed = TaxpayerFileRecord::query()->where('taxpayer_file_extraction_id', '!=', $extractionId)->limit($cap)->pluck('niu')->all();

        // Record any genuinely-new NIUs so a future re-appearance is recognised.
        if ($addedTotal > 0) {
            DB::connection($central)->statement(
                'INSERT OR IGNORE INTO taxpayer_niu_seen (niu, first_seen_at) SELECT niu, ? FROM taxpayer_file_records WHERE id > ?',
                [now()->toDateTimeString(), $maxIdBefore],
            );
        }

        return new TaxpayerChangeSet(
            addedTotal: $addedTotal,
            addedFirstTime: $addedTotal - $returningCount,
            addedReturning: $returningCount,
            removedTotal: $removedTotal,
            added: array_values(array_map(strval(...), $added)),
            removed: array_values(array_map(strval(...), $removed)),
            returning: array_values(array_map(strval(...), $returningNius)),
            capped: $addedTotal > $cap || $removedTotal > $cap,
        );
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
            /** @var array<string, ?int> $idx target column => cell index, resolved once */
            $idx = [];
            $header = true;

            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();

                if ($header) {
                    $header = false;
                    // Resolve each target column's index ONCE from the header row,
                    // instead of re-normalising header names on every cell of every
                    // row (hundreds of thousands of rows × seven columns).
                    $idx = $this->resolveColumns($cells);

                    continue;
                }

                $niu = $this->valueAt($cells, $idx['niu'] ?? null);
                if ($niu === null) {
                    continue;
                }

                $now = now();
                $buffer[] = [
                    'niu' => $niu,
                    'raison_sociale' => $this->valueAt($cells, $idx['raison_sociale'] ?? null),
                    'sigle' => $this->valueAt($cells, $idx['sigle'] ?? null),
                    'activite' => $this->valueAt($cells, $idx['activite'] ?? null),
                    'regime' => $this->valueAt($cells, $idx['regime'] ?? null),
                    'cri' => $this->valueAt($cells, $idx['cri'] ?? null),
                    'centre' => $this->valueAt($cells, $idx['centre'] ?? null),
                    'taxpayer_file_extraction_id' => $extractionId,
                    'created_at' => $now,
                    'updated_at' => $now,
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
     * Map each target column to its cell index from the header row — resolved
     * once per file. Reading by HEADER NAME (not a fixed index) keeps it robust:
     * the monthly archive (8 cols) and the live "Exporter" list (12 cols, with
     * TELEPHONE/VILLE/…) order their columns differently.
     *
     * @param  array<int, mixed>  $headerCells
     * @return array<string, ?int>
     */
    private function resolveColumns(array $headerCells): array
    {
        $columns = [];
        foreach ($headerCells as $index => $name) {
            $key = $this->headerKey($name);
            if ($key !== '') {
                $columns[$key] = $index;
            }
        }

        $idx = [];
        foreach (self::FIELDS as $field => $header) {
            $idx[$field] = $columns[$this->headerKey($header)] ?? null;
        }

        return $idx;
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function valueAt(array $cells, ?int $index): ?string
    {
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
