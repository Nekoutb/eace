<?php

declare(strict_types=1);

namespace App\Services\Acf;

use App\Domain\Acf\AcfAuthenticityStatus;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Live authenticity check. The ACF's QR code encodes a direct verification
 * deep-link — `…/authentification?doc=ACF&reference=<REFERENCE>` — so we hand
 * that URL to scripts/harmony2-acf.cjs (headless Chromium), which renders the
 * portal's verdict page and returns the outcome plus a screenshot. Any failure
 * collapses to "unverified".
 */
final class Harmony2AcfConnector implements AcfAuthenticityConnector
{
    public function __construct(
        private string $url,
        private string $chromePath,
        private ?string $nodeBinary = null,
        private int $timeoutSeconds = 120,
    ) {}

    public function verify(string $reference): AcfAuthenticityResult
    {
        // The printed reference is purely numeric (e.g. 620264856998); strip any
        // spaces/punctuation the OCR may have introduced.
        $reference = preg_replace('/\D+/', '', $reference) ?? '';
        if ($reference === '') {
            return AcfAuthenticityResult::unverified($this->url, 'empty reference');
        }

        $deepLink = $this->deepLink($reference);
        $script = base_path('scripts/harmony2-acf.cjs');
        $out = storage_path('app/tmp/acf-'.Str::random(16).'.png');
        @mkdir(dirname($out), 0775, true);

        $process = new Process([
            $this->nodeBinary ?? 'node',
            $script,
            '--url='.$deepLink,
            '--out='.$out,
            '--chrome='.$this->chromePath,
        ], base_path(), null, null, $this->timeoutSeconds);

        try {
            $process->run();

            $json = $this->lastJsonLine($process->getOutput());
            if ($json === null) {
                // Surface why the driver produced no verdict (broken toolchain,
                // pre-launch crash) instead of an opaque "no driver output".
                return AcfAuthenticityResult::unverified($deepLink, 'no driver output'.$this->diagnostics($process));
            }

            $status = match ($json['status'] ?? 'unverified') {
                'authentic' => AcfAuthenticityStatus::Authentic,
                'not_authentic' => AcfAuthenticityStatus::NotAuthentic,
                default => AcfAuthenticityStatus::Unverified,
            };

            // file_get_contents runs before the finally unlinks the temp file.
            $png = is_file($out) ? (file_get_contents($out) ?: null) : null;

            return new AcfAuthenticityResult(
                status: $status,
                url: $deepLink,
                resultText: is_string($json['text'] ?? null) ? $json['text'] : null,
                screenshotPng: $png,
            );
        } catch (ProcessTimedOutException) {
            // Slow-but-reachable portal — distinct from a genuine crash.
            return AcfAuthenticityResult::unverified($deepLink, 'driver timeout');
        } catch (Throwable $e) {
            report($e);

            return AcfAuthenticityResult::unverified($deepLink, 'driver exception');
        } finally {
            @unlink($out);
        }
    }

    /** The QR-equivalent verification URL for a numeric reference. */
    private function deepLink(string $reference): string
    {
        $base = rtrim($this->url, '?&');
        $sep = str_contains($base, '?') ? '&' : '?';

        return $base.$sep.'doc=ACF&reference='.rawurlencode($reference);
    }

    /** A short, diagnosable tail of the failed process for the audit note. */
    private function diagnostics(Process $process): string
    {
        $parts = [];
        if (! $process->isSuccessful()) {
            $parts[] = 'exit='.($process->getExitCode() ?? 'null');
        }
        $stderr = trim($process->getErrorOutput());
        if ($stderr !== '') {
            $parts[] = 'stderr='.Str::limit($stderr, 300);
        }

        return $parts === [] ? '' : ' ('.implode('; ', $parts).')';
    }

    /**
     * The driver prints a single JSON line; tolerate any leading chatter.
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
}
