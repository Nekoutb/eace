<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\TaxpayerFetchReportMail;
use App\Models\TaxpayerFileExtraction;
use App\Models\TaxpayerFileRecord;
use App\Services\Evidence\EvidenceStore;
use App\Services\Registry\DgiExportDownloader;
use App\Services\Registry\TaxpayerFileIngestionService;
use App\Services\Registry\TaxpayerFileIntegrityChecker;
use App\Services\Registry\TaxpayerIntegrityReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Refreshes the shared NIU-existence register from the DGI consultation portal.
 *
 * The CORRECT source is the LIVE export: the "Exporter" button on
 * listecontribuable.aspx dumps the ENTIRE register (~579k rows) — NOT the monthly
 * "Archives" tab. That export is a multi-tab ASP.NET ViewState postback, so it is
 * driven headlessly (DgiExportDownloader → scripts/dgi-export.cjs). A local .xlsx
 * may be ingested directly with --file. A byte-identical re-download is recorded
 * as "unchanged" rather than re-ingested.
 */
class FetchTaxpayerFile extends Command
{
    protected $signature = 'taxpayer-file:fetch
        {--file= : ingest a local .xlsx instead of downloading the live export}
        {--period= : period label for --file, e.g. "Export 13/06/2026"}';

    protected $description = 'Download the full live DGI taxpayer export (or ingest a local file) and refresh the NIU-existence register.';

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

        // Drive the portal's "Exporter" to download the ENTIRE live register.
        $this->info('Downloading the live DGI taxpayer export …');
        [$path, $error] = app(DgiExportDownloader::class)->download();

        $period = 'Export '.now()->format('d/m/Y');

        if ($path === null) {
            $this->error('Live export failed: '.$error);
            TaxpayerFileExtraction::query()->create([
                'period_label' => $period,
                'status' => 'failed',
                'message' => 'Live export failed: '.$error,
                'extracted_at' => now(),
            ]);
            $this->emailReport(new TaxpayerIntegrityReport(status: 'failed', period: $period, error: 'Live export failed: '.$error));

            return self::FAILURE;
        }

        // Archive the raw download to its own immutable Wasabi tree
        // (tax-register/<year>/<month>/<date-time>/<file>), one folder per
        // Monday/Thursday run, before it is ingested or pruned locally.
        $this->archiveDownload($path);

        $result = $this->ingestFrom($ingestion, $path, $period, (string) config('services.dgi.export_url'));

        // Keep only the latest download so the twice-weekly 52 MB exports don't pile up.
        $this->pruneOldExports($path);

        return $result;
    }

    /** Stream the downloaded export into the central tax-register archive. Never fatal. */
    private function archiveDownload(string $path): void
    {
        try {
            $ref = app(EvidenceStore::class)->putTaxRegister($path, basename($path));
            $this->info('Archived the download → '.$ref);
        } catch (Throwable $e) {
            report($e);
            $this->warn('Could not archive the download: '.$e->getMessage());
        }
    }

    private function ingestFrom(TaxpayerFileIngestionService $ingestion, string $path, string $period, ?string $url): int
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            $this->error('Could not hash the file.');
            $this->emailReport(new TaxpayerIntegrityReport(status: 'failed', period: $period, error: 'Could not hash the downloaded file.'));

            return self::FAILURE;
        }

        $relativePath = 'taxpayer-files/'.basename($path);
        $current = TaxpayerFileExtraction::current();
        $previousCount = $current?->row_count;

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
            $this->emailReport(new TaxpayerIntegrityReport(
                status: 'unchanged',
                period: $period,
                fileHash: $hash,
                distinctNiu: (int) $current->row_count,
                previousRowCount: $previousCount,
            ));

            return self::SUCCESS;
        }

        try {
            $extraction = $ingestion->ingest($path, $period, [
                'source_url' => $url,
                'file_path' => $relativePath,
                'file_hash' => $hash,
            ]);
        } catch (Throwable $e) {
            // A collapsed/corrupt file leaves the register untouched (handled in the
            // ingestion service); confirm the failure by email and exit gracefully.
            $this->error('Ingest failed: '.$e->getMessage());
            $this->emailReport(new TaxpayerIntegrityReport(
                status: 'failed', period: $period, fileHash: $hash, previousRowCount: $previousCount, error: $e->getMessage(),
            ));

            return self::FAILURE;
        }

        $this->info("Ingested {$period}: ".number_format($extraction->row_count).' taxpayers.');

        // Build + email the data-integrity report: file-level checks (schema,
        // completeness, validity, provenance) + the DB-derived distinct/duplicate
        // count + the change vs the previous snapshot.
        $metrics = app(TaxpayerFileIntegrityChecker::class)->check($path);
        $this->emailReport(new TaxpayerIntegrityReport(
            status: 'completed',
            period: $period,
            fileHash: $metrics['fileHash'],
            fileSizeBytes: $metrics['fileSizeBytes'],
            totalRows: $metrics['total'],
            rowsWithNiu: $metrics['rowsWithNiu'],
            distinctNiu: TaxpayerFileRecord::query()->count(),
            rowsWithName: $metrics['rowsWithName'],
            niuFormatValid: $metrics['niuValid'],
            columnsFound: $metrics['columnsFound'],
            columnsMissing: $metrics['columnsMissing'],
            previousRowCount: $previousCount,
        ));

        return self::SUCCESS;
    }

    /** Email the post-refresh confirmation + integrity report, if a recipient is configured. */
    private function emailReport(TaxpayerIntegrityReport $report): void
    {
        $to = config('niu.taxpayer_file.report_email');
        if (! is_string($to) || $to === '') {
            return;
        }

        try {
            Mail::to($to)->send(new TaxpayerFetchReportMail($report));
            $this->info('Integrity report emailed to '.$to.'.');
        } catch (Throwable $e) {
            report($e);
            $this->warn('Could not email the integrity report: '.$e->getMessage());
        }
    }

    /** Remove every downloaded-export directory except the one just ingested. */
    private function pruneOldExports(string $keepPath): void
    {
        $keepDir = dirname($keepPath);

        foreach (File::glob(storage_path('app/taxpayer-files/live-export-*')) as $dir) {
            if (is_dir($dir) && $dir !== $keepDir) {
                File::deleteDirectory($dir);
            }
        }
    }
}
