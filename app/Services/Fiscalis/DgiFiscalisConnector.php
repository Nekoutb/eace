<?php

declare(strict_types=1);

namespace App\Services\Fiscalis;

use App\Domain\Fiscalis\FiscalisPresence;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Live national-file consultation: shells out to scripts/dgi-niu.cjs (headless
 * Chromium) which drives the DGI "Fichier National des Contribuables" portal
 * for one NIU and returns the verdict plus a screenshot. Any failure collapses
 * to "unverified".
 */
final class DgiFiscalisConnector implements FiscalisConnector
{
    public function __construct(
        private string $url,
        private string $chromePath,
        private ?string $nodeBinary = null,
        private int $timeoutSeconds = 120,
    ) {}

    public function check(string $niu): FiscalisResult
    {
        $niu = trim($niu);
        if ($niu === '') {
            return FiscalisResult::unverified($this->url, 'empty NIU');
        }

        $script = base_path('scripts/dgi-niu.cjs');
        $out = storage_path('app/tmp/fiscalis-'.Str::random(16).'.png');
        @mkdir(dirname($out), 0775, true);

        try {
            $process = new Process([
                $this->nodeBinary ?? 'node',
                $script,
                '--niu='.$niu,
                '--out='.$out,
                '--chrome='.$this->chromePath,
                '--url='.$this->url,
            ], base_path(), null, null, $this->timeoutSeconds);

            $process->run();

            $json = $this->lastJsonLine($process->getOutput());
            if ($json === null) {
                return FiscalisResult::unverified($this->url, 'no driver output');
            }

            $presence = match ($json['status'] ?? 'unverified') {
                'present' => FiscalisPresence::Present,
                'absent' => FiscalisPresence::Absent,
                default => FiscalisPresence::Unverified,
            };

            $png = is_file($out) ? (file_get_contents($out) ?: null) : null;
            @unlink($out);

            return new FiscalisResult(
                presence: $presence,
                url: $this->url,
                count: (int) ($json['count'] ?? 0),
                resultText: is_string($json['text'] ?? null) ? $json['text'] : null,
                screenshotPng: $png,
            );
        } catch (Throwable $e) {
            report($e);
            @unlink($out);

            return FiscalisResult::unverified($this->url, 'driver exception');
        }
    }

    /**
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
