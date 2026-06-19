<?php

declare(strict_types=1);

namespace App\Services\Registry;

use App\Support\ChildProcessEnv;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Downloads the FULL live taxpayer list from the DGI registration-consultation
 * portal by driving its "Exporter" button headlessly (scripts/dgi-export.cjs).
 * The portal's export is NOT a plain URL — it is a multi-tab ASP.NET ViewState
 * postback that streams the entire register (~579k rows) as an .xlsx, so it needs
 * a real browser, not cURL. Returns the downloaded file's absolute path, or null
 * on any failure (the caller records a failed extraction).
 */
final class DgiExportDownloader
{
    public function __construct(
        private string $url,
        private string $chromePath,
        private ?string $nodeBinary = null,
        private int $timeoutSeconds = 420,
    ) {}

    /** @return array{0: ?string, 1: ?string} [absolute file path, diagnostic note] */
    public function download(): array
    {
        $script = base_path('scripts/dgi-export.cjs');
        $dir = storage_path('app/taxpayer-files/live-export-'.now()->format('Ymd-His'));
        File::ensureDirectoryExists($dir, 0775);

        $process = new Process([
            $this->nodeBinary ?? 'node',
            $script,
            '--url='.$this->url,
            '--dir='.$dir,
            '--chrome='.$this->chromePath,
            '--timeout='.$this->timeoutSeconds,
        ], base_path(), ChildProcessEnv::forNode(), null, $this->timeoutSeconds + 120);

        try {
            // The export generates hundreds of thousands of rows server-side before
            // the download starts — give the whole capture generous headroom.
            @set_time_limit($this->timeoutSeconds + 180);

            $process->run();

            $json = $this->lastJsonLine($process->getOutput());

            if ($json === null) {
                return [null, 'no driver output'.$this->diagnostics($process)];
            }

            if (($json['ok'] ?? false) !== true) {
                return [null, is_string($json['error'] ?? null) ? $json['error'] : 'driver reported failure'];
            }

            $file = $json['file'] ?? null;
            if (! is_string($file) || ! is_file($file)) {
                return [null, 'driver reported success but no file on disk'];
            }

            return [$file, null];
        } catch (ProcessTimedOutException) {
            return [null, 'export driver timed out'];
        } catch (Throwable $e) {
            report($e);

            return [null, 'export driver exception: '.$e->getMessage()];
        }
    }

    /**
     * The driver prints a single JSON line on stdout; tolerate leading chatter.
     *
     * @return array<string, mixed>|null
     */
    private function lastJsonLine(string $output): ?array
    {
        $lines = array_reverse(array_filter(array_map('trim', explode("\n", $output))));
        foreach ($lines as $line) {
            if (! str_starts_with($line, '{')) {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function diagnostics(Process $process): string
    {
        $stderr = trim($process->getErrorOutput());

        return $stderr === '' ? '' : ' ('.Str::limit($stderr, 300).')';
    }
}
