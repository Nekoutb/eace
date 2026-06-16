<?php

declare(strict_types=1);

namespace App\Services\Acf;

use App\Models\AcfCertificate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Runs the live Harmony2 authenticity check for an ACF certificate, stores the
 * verdict screenshot on the tenant disk, and writes the outcome back onto the
 * record (status, URL, note, snapshot path, last_verified_at) before
 * recomputing validity. The connector never throws, so neither does this.
 */
final class AcfAuthenticationService
{
    public function __construct(private AcfAuthenticityConnector $connector) {}

    public function authenticate(AcfCertificate $acf): AcfAuthenticityResult
    {
        $result = $this->connector->verify((string) $acf->reference);

        $disk = Storage::disk('local');
        $path = $acf->authenticity_snapshot_path;

        if ($result->hasScreenshot()) {
            // Replace any prior snapshot for this certificate.
            $path = 'acf-snapshots/acf-'.$acf->getKey().'-'.CarbonImmutable::now()->format('Ymd-His').'.png';
            $disk->put($path, (string) $result->screenshotPng);
        }

        $acf->forceFill([
            'authenticity_status' => $result->status,
            'authenticity_url' => $result->url,
            'authenticity_snapshot_path' => $path,
            'authenticity_note' => $result->resultText,
            'last_verified_at' => CarbonImmutable::now(),
        ]);
        $acf->recomputeValidity();
        $acf->save();

        return $result;
    }
}
