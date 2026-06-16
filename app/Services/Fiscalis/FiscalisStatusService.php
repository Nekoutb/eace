<?php

declare(strict_types=1);

namespace App\Services\Fiscalis;

use App\Models\Verification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Runs the live DGI national-file consultation for a verification's NIU, stores
 * the verdict screenshot on the tenant disk, and writes the outcome back onto
 * the verification (presence, snapshot path, checked-at). The connector never
 * throws, so neither does this.
 */
final class FiscalisStatusService
{
    public function __construct(private FiscalisConnector $connector) {}

    public function capture(Verification $verification): FiscalisResult
    {
        $niu = (string) ($verification->niu ?? $verification->raw_input);
        $result = $this->connector->check($niu);

        $disk = Storage::disk('local');
        $path = $verification->fiscalis_snapshot_path;

        if ($result->hasScreenshot()) {
            $path = 'fiscalis-snapshots/niu-'.$verification->getKey().'-'.CarbonImmutable::now()->format('Ymd-His').'.png';
            $disk->put($path, (string) $result->screenshotPng);
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
