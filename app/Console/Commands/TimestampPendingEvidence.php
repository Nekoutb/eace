<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Evidence\EvidenceTimestampService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Finishes RFC-3161 trusted timestamps that a Timestamp Authority outage left
 * "pending" at capture time. Runs per tenant (evidence seals are tenant-owned),
 * so a transient outage never leaves an evidence capture permanently unsealed.
 */
class TimestampPendingEvidence extends Command
{
    protected $signature = 'evidence:timestamp-pending';

    protected $description = 'Retry RFC-3161 trusted timestamps left pending by an authority outage, across all tenants.';

    public function handle(): int
    {
        if (! config('niu.timestamp.enabled')) {
            $this->info('Trusted timestamping is disabled — nothing to retry.');

            return self::SUCCESS;
        }

        $total = 0;

        Tenant::query()->cursor()->each(function (Tenant $tenant) use (&$total): void {
            tenancy()->initialize($tenant);

            try {
                $signed = app(EvidenceTimestampService::class)->retryPending();
                $total += $signed;

                if ($signed > 0) {
                    $this->info("[{$tenant->getTenantKey()}] sealed {$signed} pending capture(s).");
                }
            } catch (Throwable $e) {
                $this->warn("[{$tenant->getTenantKey()}] failed: ".$e->getMessage());
            } finally {
                tenancy()->end();
            }
        });

        $this->info("Sealed {$total} pending capture(s) in total.");

        return self::SUCCESS;
    }
}
