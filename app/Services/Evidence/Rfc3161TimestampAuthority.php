<?php

declare(strict_types=1);

namespace App\Services\Evidence;

use App\Domain\Evidence\TimestampStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * An RFC-3161 trusted-timestamp client. It builds a timestamp request for a
 * SHA-256 hash with the system openssl, POSTs it to a Timestamp Authority, and
 * keeps the signed token (the .tsr) — an independent, cryptographic proof that
 * the hashed evidence existed no later than the authority's asserted instant.
 *
 * Never throws: a TSA outage yields a "pending" result (retryable later), a hard
 * error a "failed" one, so an evidence capture is never blocked by this.
 */
final class Rfc3161TimestampAuthority implements TimestampAuthority
{
    public function __construct(
        private string $tsaUrl,
        private string $opensslBin = 'openssl',
        private string $hashAlgo = 'sha256',
        private int $httpTimeout = 12,
    ) {}

    public function timestamp(string $sha256Hex): TimestampResult
    {
        $sha256Hex = strtolower(trim($sha256Hex));
        if (! preg_match('/^[0-9a-f]{64}$/', $sha256Hex)) {
            return TimestampResult::failed($this->tsaUrl, 'invalid sha256 digest');
        }

        $dir = storage_path('app/tmp');
        @mkdir($dir, 0775, true);
        $reqPath = $dir.'/ts-'.Str::random(16).'.tsq';

        try {
            $request = $this->buildRequest($sha256Hex, $reqPath);
            if ($request === null) {
                return TimestampResult::failed($this->tsaUrl, 'could not build timestamp request (openssl)');
            }

            $response = Http::withBody($request, 'application/timestamp-query')
                ->timeout($this->httpTimeout)
                ->withHeaders(['Content-Type' => 'application/timestamp-query'])
                ->post($this->tsaUrl);

            if (! $response->successful()) {
                // An unreachable / erroring authority is transient — keep it retryable.
                return TimestampResult::pending($this->tsaUrl, 'TSA HTTP '.$response->status());
            }

            $token = $response->body();
            if ($token === '') {
                return TimestampResult::pending($this->tsaUrl, 'empty TSA response');
            }

            $genTime = $this->readGenTime($token);
            if ($genTime === null) {
                return TimestampResult::failed($this->tsaUrl, 'unparseable TSA token');
            }

            return new TimestampResult(
                status: TimestampStatus::Signed,
                tokenDer: $token,
                timestampedAt: $genTime,
                tsaUrl: $this->tsaUrl,
            );
        } catch (Throwable $e) {
            // Network/transport faults are transient → pending (retryable).
            return TimestampResult::pending($this->tsaUrl, 'TSA exception: '.Str::limit($e->getMessage(), 200));
        } finally {
            @unlink($reqPath);
        }
    }

    /** Build the DER timestamp request for the digest; return its bytes or null. */
    private function buildRequest(string $sha256Hex, string $reqPath): ?string
    {
        $process = new Process([
            $this->opensslBin, 'ts', '-query',
            '-digest', $sha256Hex,
            '-'.$this->hashAlgo,
            '-cert', // ask the TSA to embed its certificate for offline verification
            '-out', $reqPath,
        ], base_path(), null, null, 30);

        $process->run();

        // openssl may emit non-fatal config-include warnings (and a non-zero exit
        // on some hosts) yet still write a valid request — trust the produced file,
        // not the exit code. A genuine failure leaves no file.
        if (! is_file($reqPath)) {
            return null;
        }

        $bytes = file_get_contents($reqPath);

        return $bytes === false || $bytes === '' ? null : $bytes;
    }

    /** Extract the authority-asserted instant ("Time stamp:") from a token. */
    private function readGenTime(string $token): ?CarbonImmutable
    {
        $dir = storage_path('app/tmp');
        $tsrPath = $dir.'/ts-'.Str::random(16).'.tsr';

        try {
            if (file_put_contents($tsrPath, $token) === false) {
                return null;
            }

            $process = new Process([
                $this->opensslBin, 'ts', '-reply',
                '-in', $tsrPath,
                '-text',
            ], base_path(), null, null, 30);
            $process->run();

            // As with the request, tolerate non-fatal openssl config warnings:
            // read the timestamp straight from the reply's text output. A missing
            // "Time stamp:" line is the real failure signal.
            if (! preg_match('/Time stamp:\s*(.+)/', $process->getOutput(), $m)) {
                return null;
            }

            try {
                return CarbonImmutable::parse(trim($m[1]));
            } catch (Throwable) {
                return null;
            }
        } finally {
            @unlink($tsrPath);
        }
    }
}
