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

    /** Latin accent → ASCII fold, applied deterministically before sanitising a segment. */
    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Œ' => 'OE', 'Æ' => 'AE',
    ];

    /**
     * Store bytes and return the disk-prefixed reference to persist on the row.
     * Never throws: a Wasabi outage falls back to the local disk so a capture is
     * never lost. (Legacy flat scheme — prefer putEvidence() for new captures.)
     */
    public function put(string $docType, int|string|null $ref, string $filename, string $bytes): string
    {
        return $this->write($docType, $this->buildKey($docType, $ref, $filename), $bytes);
    }

    /**
     * Store one piece of evidence under the human-browsable structured scheme
     *   <tenant>/<year>/suppliers/<NIU__Vendor>/<leaf>/<descriptive file>
     * so the bucket reads as an auditor would navigate it: by year, then vendor,
     * then invoice (recording) or payment date (settlement).
     */
    public function putEvidence(EvidenceContext $context, string $filename, string $bytes, string $docType = self::TYPE_EVIDENCE): string
    {
        return $this->write($docType, $this->buildEvidenceKey($context, $filename), $bytes);
    }

    /**
     * Stage transient bytes on the local disk (never WORM) — a capture taken
     * during interactive scanning that is only committed to its final structured,
     * immutable home once the verification (with its vendor + invoice) exists.
     */
    public function putStaging(string $name, string $bytes): string
    {
        $key = 'staging/'.Str::lower(Str::random(12)).'/'.$this->safeFile($name);
        Storage::disk('local')->put($key, $bytes);

        return 'local'.self::SEP.$key;
    }

    /** Co-locate a timestamp token beside the evidence it seals (same disk + WORM scheme). */
    public function putSeal(string $evidenceRef, string $bytes): string
    {
        [$disk, $key] = $this->resolve($evidenceRef);
        if ($key === null) {
            return $this->put(self::TYPE_MANIFESTS, null, 'seal.tsr', $bytes);
        }

        $sealKey = $key.'.tsr';

        if ($disk === 'wasabi' && $this->wasabiEnabled()) {
            try {
                $this->putToWasabi(self::TYPE_EVIDENCE, $sealKey, $bytes);

                return 'wasabi'.self::SEP.$sealKey;
            } catch (Throwable $e) {
                report($e);
            }
        }

        Storage::disk('local')->put($sealKey, $bytes);

        return 'local'.self::SEP.$sealKey;
    }

    /** Delete a stored reference — used to prune local staging once it is committed. */
    public function delete(?string $ref): void
    {
        [$disk, $key] = $this->resolve($ref);
        if ($key === null) {
            return;
        }

        try {
            Storage::disk($disk)->delete($key);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Shared write path: Wasabi (WORM) when enabled, else the local disk. Never throws. */
    private function write(string $docType, string $key, string $bytes): string
    {
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

    /**
     * The structured, human-browsable key for one evidence file:
     *   <tenant>/<year>/suppliers/<NIU__Vendor>/<leaf>/<descriptive file>
     */
    private function buildEvidenceKey(EvidenceContext $context, string $filename): string
    {
        $tenant = tenant() !== null ? (string) tenant()->getTenantKey() : 'central';

        return sprintf(
            '%s/%s/suppliers/%s/%s/%s',
            $tenant,
            $context->year(),
            $context->supplierSegment(),
            trim($context->leaf, '/'),
            $this->safeFile($filename),
        );
    }

    /** The folder prefix for a context — list this to find a vendor+invoice's evidence. */
    public function prefixFor(EvidenceContext $context, ?string $tenantId = null): string
    {
        $tenant = $tenantId ?? (tenant() !== null ? (string) tenant()->getTenantKey() : 'central');

        return sprintf('%s/%s/suppliers/%s/%s/', $tenant, $context->year(), $context->supplierSegment(), trim($context->leaf, '/'));
    }

    /**
     * Every object key under a prefix on the active evidence disk — the query primitive.
     *
     * @return array<int, string>
     */
    public function listKeys(string $prefix): array
    {
        $disk = $this->wasabiEnabled() ? (string) config('niu.wasabi.disk', 'wasabi') : 'local';

        try {
            return Storage::disk($disk)->allFiles($prefix);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /** Keep the descriptive filename but strip anything unsafe for a key (no random prefix). */
    private function safeFile(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_BASENAME);
        $clean = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);

        return $clean !== '' ? $clean : 'file';
    }

    /**
     * A path-safe, deterministic segment from a vendor name / reference: accents
     * folded to ASCII, spaces and punctuation to underscores, collapsed + trimmed.
     * Returns $fallback when nothing usable remains.
     */
    public static function sanitize(?string $value, string $fallback = ''): string
    {
        $v = strtr((string) ($value ?? ''), self::ACCENTS);
        $v = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $v);
        $v = (string) preg_replace('/_{2,}/', '_', $v);
        $v = trim($v, '_-.');

        return $v !== '' ? $v : $fallback;
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
