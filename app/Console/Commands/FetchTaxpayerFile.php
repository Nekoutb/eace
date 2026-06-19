<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TaxpayerFileExtraction;
use App\Services\Registry\TaxpayerFileIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Refreshes the offline NIU-existence source from the DGI consultation portal.
 * Scheduled weekly (~52 extractions a year). Downloads the most recent published
 * "FICHIER <MOIS> AAAA.xlsx", or ingests a local file passed with --file. If the
 * downloaded file is byte-identical to the last extraction it is recorded as
 * "unchanged" rather than re-ingested.
 */
class FetchTaxpayerFile extends Command
{
    protected $signature = 'taxpayer-file:fetch
        {--file= : ingest a local .xlsx instead of downloading}
        {--period= : period label for --file, e.g. "MARS 2026"}
        {--month= : force a specific published month, e.g. "MARS 2026"}';

    protected $description = 'Download (or ingest) the DGI monthly taxpayer file and refresh the NIU-existence source.';

    private const BASE = 'https://teledeclaration-dgi.cm/UploadedFiles/AttachedFiles/ArchiveListecontribuable/';

    private const MONTHS = ['JANVIER', 'FEVRIER', 'MARS', 'AVRIL', 'MAI', 'JUIN', 'JUILLET', 'AOUT', 'SEPTEMBRE', 'OCTOBRE', 'NOVEMBRE', 'DECEMBRE'];

    public function handle(TaxpayerFileIngestionService $ingestion): int
    {
        // One refresh at a time — the scheduled run (Mon/Thu) and any on-demand
        // "Update register" click share this lock, so they never collide or
        // double-ingest. Auto-expires after 30 min if a run dies mid-flight.
        $lock = Cache::lock('taxpayer-file:fetch', 1800);

        if (! $lock->get()) {
            $this->warn('Another taxpayer-file refresh is already running; skipping.');

            return self::SUCCESS;
        }

        try {
            return $this->fetch($ingestion);
        } finally {
            $lock->release();
        }
    }

    private function fetch(TaxpayerFileIngestionService $ingestion): int
    {
        $local = $this->option('file');

        if (filled($local)) {
            $path = (string) $local;
            if (! is_file($path)) {
                $this->error("File not found: {$path}");

                return self::FAILURE;
            }
            $period = filled($this->option('period')) ? (string) $this->option('period') : pathinfo($path, PATHINFO_FILENAME);

            return $this->ingestFrom($ingestion, $path, $period, null);
        }

        [$url, $period] = $this->locateLatest();

        if ($url === null) {
            $this->error('No published taxpayer file found in the last 6 months.');
            TaxpayerFileExtraction::query()->create([
                'period_label' => $period !== '' ? $period : 'unknown',
                'status' => 'failed',
                'message' => 'No published file located.',
                'extracted_at' => now(),
            ]);

            return self::FAILURE;
        }

        $dir = storage_path('app/taxpayer-files');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $dest = $dir.'/'.str_replace(' ', '-', $period).'.xlsx';

        $this->info("Downloading {$period} …");
        $response = Http::timeout(600)->sink($dest)->get($url);

        if (! $response->successful()) {
            $this->error("Download failed: HTTP {$response->status()}");

            return self::FAILURE;
        }

        return $this->ingestFrom($ingestion, $dest, $period, $url);
    }

    private function ingestFrom(TaxpayerFileIngestionService $ingestion, string $path, string $period, ?string $url): int
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            $this->error('Could not hash the file.');

            return self::FAILURE;
        }

        $relativePath = 'taxpayer-files/'.basename($path);
        $current = TaxpayerFileExtraction::current();

        if ($current !== null && $current->file_hash === $hash) {
            TaxpayerFileExtraction::query()->create([
                'period_label' => $period,
                'source_url' => $url,
                'file_path' => $relativePath,
                'file_hash' => $hash,
                'row_count' => $current->row_count,
                'status' => 'unchanged',
                'extracted_at' => now(),
            ]);
            $this->info("Taxpayer file unchanged ({$period}) — recorded, {$current->row_count} taxpayers.");

            return self::SUCCESS;
        }

        $extraction = $ingestion->ingest($path, $period, [
            'source_url' => $url,
            'file_path' => $relativePath,
            'file_hash' => $hash,
        ]);

        $this->info("Ingested {$period}: ".number_format($extraction->row_count).' taxpayers.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function locateLatest(): array
    {
        if (filled($this->option('month'))) {
            $period = strtoupper((string) $this->option('month'));

            return [$this->url($period), $period];
        }

        $cursor = CarbonImmutable::now();
        $lastTried = '';

        for ($i = 0; $i < 6; $i++) {
            $period = self::MONTHS[(int) $cursor->format('n') - 1].' '.$cursor->format('Y');
            $lastTried = $period;
            $url = $this->url($period);

            if (Http::timeout(30)->head($url)->successful()) {
                return [$url, $period];
            }

            $cursor = $cursor->subMonth();
        }

        return [null, $lastTried];
    }

    private function url(string $period): string
    {
        return self::BASE.'FICHIER%20'.str_replace(' ', '%20', $period).'.xlsx';
    }
}
