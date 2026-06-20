<?php

declare(strict_types=1);

namespace App\Services\Evidence;

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single gateway for storing and retrieving compliance documents — evidence
 * screenshots, invoices, ACFs, manifests and timestamp tokens.
 *
 * When Wasabi is enabled it writes to the immutable, encrypted S3 bucket under a
 * per-tenant, prefix-addressable key scheme
 *   t/<tenant_id>/<doc_type>/<YYYY>/<MM>/v_<ref>/<file>
 * with Object Lock (COMPLIANCE WORM for evidence + manifests, GOVERNANCE for the
 * rest) and AES-256 SSE — every read/write confined to the active tenant's
 * t/<id>/ prefix. When Wasabi is off (dev, tests, or before credentials are set)
 * it transparently falls back to the local disk, so the app works either way.
 *
 * The reference persisted on the DB row is a disk-prefixed pseudo-key —
 * "wasabi::t/eace/evidence/2026/06/v_10487/ab-fiscalis.png" or
 * "local::fiscalis-snapshots/…" — so retrieval always knows where to read. A
 * bare path (no "::") is a legacy local file written before Wasabi.
 */
final class EvidenceStore
{
    private const SEP = '::';

    /** Doc types are part of the key; these get true-WORM COMPLIANCE locking. */
    public const TYPE_EVIDENCE = 'evidence';

    public const TYPE_INVOICES = 'invoices';

    public const TYPE_ACF = 'acf';

    public const TYPE_MANIFESTS = 'manifests';

    public const TYPE_EXPORTS = 'exports';

    /**
     * Store bytes and return the disk-prefixed reference to persist on the row.
     * Never throws: a Wasabi outage falls back to the local disk so a capture is
     * never lost.
     */
    public function put(string $docType, int|string|null $ref, string $filename, string $bytes): string
    {
        $key = $this->buildKey($docType, $ref, $filename);

        if ($this->wasabiEnabled()) {
            try {
                $this->putToWasabi($docType, $key, $bytes);

                return 'wasabi'.self::SEP.$key;
            } catch (Throwable $e) {
                report($e);
                // fall through to local so the capture is not lost
            }
        }

        Storage::disk('local')->put($key, $bytes);

        return 'local'.self::SEP.$key;
    }

    /** Read bytes for a stored reference (or a legacy bare local path). */
    public function get(?string $ref): ?string
    {
        [$disk, $key] = $this->resolve($ref);
        if ($key === null) {
            return null;
        }

        try {
            return Storage::disk($disk)->exists($key) ? Storage::disk($disk)->get($key) : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function exists(?string $ref): bool
    {
        [$disk, $key] = $this->resolve($ref);

        try {
            return $key !== null && Storage::disk($disk)->exists($key);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * A base64 data-URI for inline display/download of a stored image, or null.
     * (Evidence screenshots are small; the audit page renders them inline.)
     */
    public function dataUri(?string $ref, string $mime = 'image/png'): ?string
    {
        $bytes = $this->get($ref);

        return $bytes === null ? null : 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    /**
     * The raw [disk, key] for a stored reference; a legacy bare path is local.
     *
     * @return array{0: string, 1: ?string}
     */
    public function resolve(?string $ref): array
    {
        if (! filled($ref)) {
            return ['local', null];
        }

        if (str_contains((string) $ref, self::SEP)) {
            [$disk, $key] = explode(self::SEP, (string) $ref, 2);

            return [$disk, $key];
        }

        return ['local', $ref];
    }

    private function buildKey(string $docType, int|string|null $ref, string $filename): string
    {
        $tenant = tenant() !== null ? (string) tenant()->getTenantKey() : 'central';
        $now = CarbonImmutable::now();
        $refSeg = $ref !== null && $ref !== '' ? 'v_'.$ref : 'misc';

        return sprintf(
            't/%s/%s/%s/%s/%s/%s',
            $tenant,
            $docType,
            $now->format('Y'),
            $now->format('m'),
            $refSeg,
            $this->safeName($filename),
        );
    }

    /** A collision-proof, path-safe object name. */
    private function safeName(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_BASENAME);
        $clean = ltrim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $base), '-');

        return Str::lower(Str::random(8)).'-'.($clean !== '' ? $clean : 'file');
    }

    private function wasabiEnabled(): bool
    {
        return (bool) config('niu.wasabi.enabled') && filled(config('filesystems.disks.wasabi.bucket'));
    }

    /** Write to Wasabi with per-type Object Lock + server-side encryption. */
    private function putToWasabi(string $docType, string $key, string $bytes): void
    {
        /** @var AwsS3V3Adapter $disk */
        $disk = Storage::disk(config('niu.wasabi.disk', 'wasabi'));
        $client = $disk->getClient();

        $params = [
            'Bucket' => (string) config('filesystems.disks.wasabi.bucket'),
            'Key' => $key,
            'Body' => $bytes,
            'ServerSideEncryption' => (string) config('niu.wasabi.sse', 'AES256'),
        ];

        if ((bool) config('niu.wasabi.object_lock')) {
            $compliance = in_array($docType, (array) config('niu.wasabi.compliance_types', []), true);
            $params['ObjectLockMode'] = $compliance ? 'COMPLIANCE' : 'GOVERNANCE';
            $params['ObjectLockRetainUntilDate'] = CarbonImmutable::now()
                ->addYears((int) config('niu.wasabi.retention_years', 2));
        }

        $client->putObject($params);
    }
}
