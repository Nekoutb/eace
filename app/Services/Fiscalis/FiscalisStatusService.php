<?php

declare(strict_types=1);

namespace App\Services\Fiscalis;

use App\Models\Verification;
use App\Services\Evidence\EvidenceContext;
use App\Services\Evidence\EvidenceNaming;
use App\Services\Evidence\EvidenceStore;
use App\Services\Evidence\EvidenceTimestampService;
use Carbon\CarbonImmutable;

/**
 * Runs the live DGI national-file consultation for a verification's NIU, stores
 * the verdict screenshot via EvidenceStore (immutable Wasabi when enabled, else
 * the local disk), and writes the outcome back onto the verification (presence,
 * snapshot reference, checked-at). The connector never throws, so neither does this.
 */
final class FiscalisStatusService
{
    public function __construct(
        private FiscalisConnector $connector,
        private EvidenceTimestampService $timestamps,
        private EvidenceStore $store,
    ) {}

    public function capture(Verification $verification): FiscalisResult
    {
        $niu = (string) ($verification->niu ?? $verification->raw_input);
        $result = $this->connector->check($niu);

        $path = $verification->fiscalis_snapshot_path;

        if ($result->hasScreenshot()) {
            $bytes = (string) $result->screenshotPng;
            $context = EvidenceContext::forVerification($verification);
            $path = $this->store->putEvidence(
                $context,
                EvidenceNaming::niuStatus($result->presence->value, $niu, $context),
                $bytes,
                EvidenceStore::TYPE_EVIDENCE,
            );
            $this->timestamps->seal($path, $bytes);
        }

        $verification->forceFill([
            'fiscalis_presence' => $result->presence->value,
            'fiscalis_snapshot_path' => $path,
            'fiscalis_checked_at' => CarbonImmutable::now(),
        ]);
        $verification->save();

        return $result;
    }
}
