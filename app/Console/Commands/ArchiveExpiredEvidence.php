<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Evidence\EvidenceArchiveService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Retention job: across every tenant, purge the bulky evidence of verifications
 * older than the retention window (keeping the tamper-proof manifest) to free
 * space. Disabled unless niu.retention_months is set — evidence is never deleted
 * until the operator opts in. Export the evidence from the Audit Log first.
 */
class ArchiveExpiredEvidence extends Command
{
    protected $signature = 'audit:archive-expired {--months= : override the retention window in months}';

    protected $description = 'Purge bulky audit evidence older than the retention window, keeping the manifest.';

    public function handle(EvidenceArchiveService $service): int
    {
        $months = $this->option('months') !== null
            ? (int) $this->option('months')
            : config('niu.retention_months');

        if (! is_int($months) || $months <= 0) {
            $this->warn('Retention is disabled (set NIU_RETENTION_MONTHS to enable the automatic purge).');

            return self::SUCCESS;
        }

        $total = 0;

        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($service, $months, &$total): void {
                foreach ($service->eligibleForArchival($months) as $verification) {
                    $service->archive($verification);
                    $total++;
                }
            });
        }

        $this->info("Archived {$total} verification(s) older than {$months} months — PDFs purged, manifest kept.");

        return self::SUCCESS;
    }
}
