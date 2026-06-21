<?php

declare(strict_types=1);

namespace App\Services\Acf;

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Models\AcfAuthentication;
use App\Models\AcfCertificate;
use App\Services\Evidence\EvidenceContext;
use App\Services\Evidence\EvidenceNaming;
use App\Services\Evidence\EvidenceStore;
use App\Services\Evidence\EvidenceTimestampService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Confirms an ACF's authenticity and writes the verdict (status, URL, note,
 * snapshot, last_verified_at) onto the certificate, then recomputes validity.
 *
 * Cross-customer optimisation: an ACF's authenticity is a global fact, so the
 * first DEFINITIVE Harmony2 verdict for a given 620… reference — obtained by any
 * workspace — is cached centrally (AcfAuthentication) and reused by every other
 * workspace that later checks the same certificate, instead of driving the
 * portal again. Validity/expiry is always recomputed locally per certificate, so
 * a reused-but-now-expired ACF still reads as inactive. The connector never
 * throws, so neither does this.
 */
final class AcfAuthenticationService
{
    public function __construct(
        private AcfAuthenticityConnector $connector,
        private EvidenceTimestampService $timestamps,
        private EvidenceStore $store,
    ) {}

    public function authenticate(AcfCertificate $acf): AcfAuthenticityResult
    {
        $reference = (string) $acf->reference;

        $cached = AcfAuthentication::query()->where('reference', $reference)->first();
        if ($cached !== null && $cached->isDefinitive()) {
            return $this->applyCached($acf, $cached);
        }

        $result = $this->connector->verify($reference);
        $this->writeOntoCertificate($acf, $result, $this->storeTenantSnapshot($acf, $result), CarbonImmutable::now());

        // Cache only definitive verdicts — a portal outage ("unverified") must
        // stay retryable for the next workspace.
        if ($result->status !== AcfAuthenticityStatus::Unverified) {
            $this->remember($reference, $acf->niu, $result);
        }

        return $result;
    }

    /**
     * Reuse a verdict already obtained for this reference: copy the canonical
     * evidence onto the tenant disk and stamp the certificate with the original
     * verdict + check date — no portal call.
     */
    private function applyCached(AcfCertificate $acf, AcfAuthentication $cached): AcfAuthenticityResult
    {
        $tenantPath = $acf->authenticity_snapshot_path;

        if (filled($cached->snapshot_path) && Storage::disk('central')->exists($cached->snapshot_path)) {
            $bytes = (string) Storage::disk('central')->get($cached->snapshot_path);
            $context = EvidenceContext::forAcf($acf);
            $tenantPath = $this->store->putEvidence(
                $context,
                EvidenceNaming::acfAuthentic((string) $acf->reference, $context),
                $bytes,
                EvidenceStore::TYPE_ACF,
            );
            $this->timestamps->seal($tenantPath, $bytes);
        }

        $this->writeOntoCertificate(
            $acf,
            new AcfAuthenticityResult($cached->authenticity_status, (string) ($cached->authenticity_url ?? ''), $cached->result_text),
            $tenantPath,
            $cached->checked_at,
        );

        return new AcfAuthenticityResult(
            status: $cached->authenticity_status,
            url: (string) ($cached->authenticity_url ?? ''),
            resultText: $cached->result_text,
            screenshotPng: null,
            reusedAt: $cached->checked_at,
        );
    }

    /** Store the live verdict screenshot on the tenant disk; return its path (or keep the existing one). */
    private function storeTenantSnapshot(AcfCertificate $acf, AcfAuthenticityResult $result): ?string
    {
        if (! $result->hasScreenshot()) {
            return $acf->authenticity_snapshot_path;
        }

        $bytes = (string) $result->screenshotPng;
        $context = EvidenceContext::forAcf($acf);
        $path = $this->store->putEvidence(
            $context,
            EvidenceNaming::acfAuthentic((string) $acf->reference, $context),
            $bytes,
            EvidenceStore::TYPE_ACF,
        );
        $this->timestamps->seal($path, $bytes);

        return $path;
    }

    private function writeOntoCertificate(AcfCertificate $acf, AcfAuthenticityResult $result, ?string $snapshotPath, CarbonImmutable $verifiedAt): void
    {
        // Preserve the QR deep-link captured at ingestion; only adopt the
        // connector's URL when it is a real verification deep-link (carries the
        // reference), so a failed/fake check never wipes the cert's known URL.
        $authUrl = str_contains($result->url, 'reference=')
            ? $result->url
            : ($acf->authenticity_url ?: $result->url);

        $acf->forceFill([
            'authenticity_status' => $result->status,
            'authenticity_url' => $authUrl,
            'authenticity_snapshot_path' => $snapshotPath,
            'authenticity_note' => $result->resultText,
            'last_verified_at' => $verifiedAt,
        ]);
        $acf->recomputeValidity();
        $acf->save();
    }

    /** Write the definitive verdict to the shared central cache for reuse by every workspace. */
    private function remember(string $reference, ?string $niu, AcfAuthenticityResult $result): void
    {
        $snapshotPath = null;
        if ($result->hasScreenshot()) {
            $snapshotPath = 'acf-authentications/'.preg_replace('/[^A-Za-z0-9]/', '', $reference).'-'.CarbonImmutable::now()->format('Ymd-His').'.png';
            Storage::disk('central')->put($snapshotPath, (string) $result->screenshotPng);
        }

        AcfAuthentication::query()->updateOrCreate(
            ['reference' => $reference],
            [
                'niu' => $niu,
                'authenticity_status' => $result->status,
                'result_text' => $result->resultText,
                'authenticity_url' => $result->url,
                'snapshot_path' => $snapshotPath,
                'checked_at' => CarbonImmutable::now(),
            ],
        );
    }
}
