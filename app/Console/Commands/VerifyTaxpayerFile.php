<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TaxpayerFileExtraction;
use App\Models\TaxpayerFileRecord;
use Illuminate\Console\Command;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Data-integrity audit between a taxpayer file and what was ingested: row/NIU
 * counts, duplicates, blanks, the in-file/in-DB diff, a field-level spot check,
 * and the file SHA-256 vs the recorded extraction hash. Read-only.
 */
class VerifyTaxpayerFile extends Command
{
    protected $signature = 'taxpayer-file:verify {--file= : xlsx to check (defaults to the latest extraction file)} {--samples=8}';

    protected $description = 'Cross-check an ingested taxpayer file against the database.';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $extraction = TaxpayerFileExtraction::current();
        $file = $this->option('file') !== null
            ? (string) $this->option('file')
            : ($extraction?->file_path !== null ? storage_path('app/'.$extraction->file_path) : null);

        if ($file === null || ! is_file($file)) {
            $this->error('File not found. Pass --file= or ingest a file first.');

            return self::FAILURE;
        }

        $sampleSize = max(1, (int) $this->option('samples'));

        $this->info('Streaming the file…');
        [$fileRows, $blanks, $fileNius, $samples] = $this->scanFile($file, $sampleSize);
        $fileDistinct = count($fileNius);
        $duplicates = $fileRows - $fileDistinct;

        $this->info('Loading ingested NIUs…');
        $dbNius = TaxpayerFileRecord::query()->pluck('niu')->flip()->all();
        $dbCount = count($dbNius);

        $missing = array_diff_key($fileNius, $dbNius);   // in file, not ingested
        $extra = array_diff_key($dbNius, $fileNius);     // ingested, not in file

        // Field-level spot check on the sampled rows.
        $sampleFails = 0;
        foreach ($samples as $sample) {
            $record = TaxpayerFileRecord::query()->where('niu', $sample['niu'])->first();
            if ($record === null || (string) $record->raison_sociale !== (string) $sample['raison_sociale']) {
                $sampleFails++;
            }
        }

        $fileHash = hash_file('sha256', $file);
        $hashMatches = $extraction !== null && $extraction->file_hash !== null
            ? ($extraction->file_hash === $fileHash ? 'match' : 'MISMATCH')
            : 'n/a';

        $this->newLine();
        $this->table(['Integrity check', 'Result'], [
            ['File data rows', number_format($fileRows)],
            ['Distinct NIUs in file', number_format($fileDistinct)],
            ['Duplicate NIUs in file', number_format($duplicates)],
            ['Blank NIU rows (skipped)', number_format($blanks)],
            ['Ingested records (DB)', number_format($dbCount)],
            ['Distinct file NIUs == DB?', $fileDistinct === $dbCount ? 'YES' : 'NO ('.number_format($fileDistinct - $dbCount).' diff)'],
            ['In file but NOT ingested', number_format(count($missing))],
            ['Ingested but NOT in file', number_format(count($extra))],
            ['Field spot check ('.$sampleSize.' rows)', $sampleFails === 0 ? 'all match' : $sampleFails.' mismatch'],
            ['File SHA-256', $fileHash],
            ['vs recorded extraction hash', $hashMatches],
        ]);

        $ok = $fileDistinct === $dbCount && count($missing) === 0 && count($extra) === 0 && $sampleFails === 0;
        $ok ? $this->info('INTEGRITY OK — the database faithfully reflects the file.')
            : $this->warn('INTEGRITY ISSUES found — see the rows above.');

        if ($missing !== []) {
            $this->line('  e.g. missing: '.implode(', ', array_slice(array_keys($missing), 0, 5)));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int,2:array<string,true>,3:array<int,array{niu:string,raison_sociale:?string}>}
     */
    private function scanFile(string $path, int $sampleSize): array
    {
        $reader = new Reader;
        $reader->open($path);

        $rows = 0;
        $blanks = 0;
        /** @var array<string, true> $nius */
        $nius = [];
        /** @var array<int, array{niu:string,raison_sociale:?string}> $samples */
        $samples = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            /** @var array<string,int> $columns */
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

                $niu = $this->value($cells, $columns, 'NIU');
                if ($niu === null) {
                    $blanks++;

                    continue;
                }

                $rows++;
                $nius[$niu] = true;

                // Reservoir sampling for the field spot check.
                if (count($samples) < $sampleSize) {
                    $samples[] = ['niu' => $niu, 'raison_sociale' => $this->value($cells, $columns, 'RAISON_SOCIALE')];
                } elseif (mt_rand(1, $rows) <= $sampleSize) {
                    $samples[mt_rand(0, $sampleSize - 1)] = ['niu' => $niu, 'raison_sociale' => $this->value($cells, $columns, 'RAISON_SOCIALE')];
                }
            }

            break;
        }

        $reader->close();

        return [$rows, $blanks, $nius, $samples];
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<string, int>  $columns
     */
    private function value(array $cells, array $columns, string $header): ?string
    {
        $index = $columns[$this->headerKey($header)] ?? null;
        if ($index === null) {
            return null;
        }

        $raw = $cells[$index] ?? null;
        if (! is_scalar($raw)) {
            return null;
        }

        $trimmed = trim((string) $raw);

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
