<?php

declare(strict_types=1);

namespace App\Services\Acf;

use App\Domain\Acf\AcfAuthenticityStatus;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Live authenticity check: shells out to scripts/harmony2-acf.cjs (headless
 * Chromium) which drives the DGI Harmony2 portal for one reference and returns
 * the verdict plus a screenshot. Any failure collapses to "unverified".
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
        $reference = trim($reference);
        if ($reference === '') {
            return AcfAuthenticityResult::unverified($this->url, 'empty reference');
        }

        $script = base_path('scripts/harmony2-acf.cjs');
        $out = storage_path('app/tmp/acf-'.Str::random(16).'.png');
        @mkdir(dirname($out), 0775, true);

        try {
            $process = new Process([
                $this->nodeBinary ?? 'node',
                $script,
                '--reference='.$reference,
                '--out='.$out,
                '--chrome='.$this->chromePath,
                '--url='.$this->url,
                '--type=acf',
            ], base_path(), null, null, $this->timeoutSeconds);

            $process->run();

            $json = $this->lastJsonLine($process->getOutput());
            if ($json === null) {
                return AcfAuthenticityResult::unverified($this->url, 'no driver output');
            }

            $status = match ($json['status'] ?? 'unverified') {
                'authentic' => AcfAuthenticityStatus::Authentic,
                'not_authentic' => AcfAuthenticityStatus::NotAuthentic,
                default => AcfAuthenticityStatus::Unverified,
            };

            $png = is_file($out) ? (file_get_contents($out) ?: null) : null;
            @unlink($out);

            return new AcfAuthenticityResult(
                status: $status,
                url: $this->url,
                resultText: is_string($json['text'] ?? null) ? $json['text'] : null,
                screenshotPng: $png,
            );
        } catch (Throwable $e) {
            report($e);
            @unlink($out);

            return AcfAuthenticityResult::unverified($this->url, 'driver exception');
        }
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
