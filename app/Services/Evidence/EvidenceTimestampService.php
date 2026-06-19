<?php

declare(strict_types=1);

namespace App\Services\Evidence;

use App\Domain\Evidence\TimestampStatus;
use App\Models\EvidenceTimestamp;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Seals evidence captures with an independent RFC-3161 trusted timestamp. For
 * each stored screenshot it records the SHA-256 and, when timestamping is on,
 * obtains and stores a signed token proving the image existed at the authority's
 * asserted instant. Always best-effort — a timestamp-authority outage never
 * blocks a capture; the seal is left "pending" for the retry job to finish.
 */
final class EvidenceTimestampService
{
    public function __construct(private TimestampAuthority $authority) {}

    /**
     * Record (and, if enabled, seal) the trusted timestamp for a stored image.
     * Returns the seal row; never throws.
     */
    public function seal(string $disk, string $path, string $bytes): EvidenceTimestamp
    {
        $row = EvidenceTimestamp::query()->firstOrNew(['disk' => $disk, 'path' => $path]);
        $row->sha256 = hash('sha256', $bytes);
        $row->algo = 'sha256';

        if (! (bool) config('niu.timestamp.enabled')) {
            $row->status = TimestampStatus::Disabled;
            $row->save();

            return $row;
        }

        return $this->attempt($row);
    }

    /**
     * Re-attempt seals the authority could not complete earlier (an outage), up
     * to the configured ceiling. Returns how many became signed.
     */
    public function retryPending(): int
    {
        if (! (bool) config('niu.timestamp.enabled')) {
            return 0;
        }

        $max = (int) config('niu.timestamp.max_attempts', 5);
        $signed = 0;

        EvidenceTimestamp::query()
            ->whereIn('status', [TimestampStatus::Pending->value, TimestampStatus::Failed->value])
            ->where('attempts', '<', $max)
            ->orderBy('id')
            ->each(function (EvidenceTimestamp $row) use (&$signed): void {
                if ($this->attempt($row)->isSigned()) {
                    $signed++;
                }
            });

        return $signed;
    }

    /** One sealing attempt against the authority; folds the verdict onto the row. */
    private function attempt(EvidenceTimestamp $row): EvidenceTimestamp
    {
        $row->attempts = $row->attempts + 1;

        try {
            $result = $this->authority->timestamp($row->sha256);

            if ($result->isSigned()) {
                $tokenPath = $row->path.'.tsr';
                Storage::disk($row->disk)->put($tokenPath, (string) $result->tokenDer);

                $row->token_path = $tokenPath;
                $row->timestamped_at = $result->timestampedAt;
                $row->tsa_url = $result->tsaUrl;
                $row->status = TimestampStatus::Signed;
                $row->last_error = null;
            } else {
                $row->status = $result->status;
                $row->tsa_url = $result->tsaUrl ?? $row->tsa_url;
                $row->last_error = $result->error;
            }
        } catch (Throwable $e) {
            // Storing the token or any unexpected fault — keep it retryable.
            $row->status = TimestampStatus::Pending;
            $row->last_error = 'seal exception: '.$e->getMessage();
        }

        $row->save();

        return $row;
    }
}
