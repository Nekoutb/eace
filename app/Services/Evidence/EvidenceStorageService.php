<?php

declare(strict_types=1);

namespace App\Services\Evidence;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Measures how much evidence storage the current tenant is using. The tenant
 * 'local' disk is rooted at this workspace's own folder (stancl suffixes it), so
 * summing its files gives the workspace's evidence footprint — the snapshots,
 * scanned invoices/ACFs and archives.
 */
final class EvidenceStorageService
{
    public function usedBytes(): int
    {
        $disk = Storage::disk('local');
        $total = 0;

        try {
            foreach ($disk->allFiles() as $file) {
                $total += $disk->size($file);
            }
        } catch (Throwable) {
            return 0;
        }

        return $total;
    }

    public function usedMb(): float
    {
        return $this->usedBytes() / 1_048_576;
    }

    /** Whole-percent of the allocated quota in use (can exceed 100). */
    public function percentUsed(int $allocatedMb): int
    {
        if ($allocatedMb <= 0) {
            return 0;
        }

        return (int) round($this->usedMb() / $allocatedMb * 100);
    }
}
