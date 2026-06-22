<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DataAccessLog;
use Illuminate\Console\Command;

/**
 * Surfaces the data-retention posture (Loi 2024/017 art. 13/28) and, with --prune,
 * rotates out only the rotatable operational metadata (read-level access logs).
 *
 * It NEVER deletes audit-grade evidence: that stays governed by the (opt-in)
 * `niu.retention_months` purge and the Wasabi Object-Lock window, which require a
 * deliberate retention decision before any evidence is removed.
 */
class DataRetention extends Command
{
    protected $signature = 'data:retention {--prune : Also rotate out access logs past their retention}';

    protected $description = 'Report the data-retention posture and optionally rotate access logs.';

    public function handle(): int
    {
        $this->info('Data-retention posture (Loi 2024/017 art. 13/28):');

        $this->line('- Evidence (write-once): '.(int) config('niu.wasabi.retention_years', 2).' year(s) — Object Lock COMPLIANCE on evidence/manifests, GOVERNANCE on invoices/ACF.');

        $retentionMonths = config('niu.retention_months');
        $this->line('- Evidence-PDF purge: '.($retentionMonths !== null
            ? (int) $retentionMonths.' months (tamper-proof manifest kept)'
            : 'DISABLED — no automatic evidence deletion until a retention period is set'));

        $accessMonths = (int) config('niu.data_protection.access_log_retention_months', 24);
        $this->line('- Access logs: '.$accessMonths.' months.');
        $this->line('- Pending sign-ups: '.(int) config('niu.pending_signup_expiry_hours', 24).' hours.');

        $cutoff = now()->subMonths($accessMonths);
        $stale = DataAccessLog::query()->where('created_at', '<', $cutoff)->count();
        $this->line("Access-log entries older than {$accessMonths} months: {$stale}.");

        if ($this->option('prune') && $stale > 0) {
            DataAccessLog::query()->where('created_at', '<', $cutoff)->delete();
            $this->info("Rotated out {$stale} access-log entries.");
        }

        return self::SUCCESS;
    }
}
