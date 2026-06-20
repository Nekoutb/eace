<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PendingSignup;
use Illuminate\Console\Command;

/**
 * Removes self-service sign-ups whose email-confirmation window has elapsed. A
 * pending row is only a holding record (no workspace exists yet), so dropping it
 * simply frees the email to start over. Scheduled daily.
 */
class PruneExpiredSignups extends Command
{
    protected $signature = 'signup:prune-expired';

    protected $description = 'Delete self-service sign-ups whose email-confirmation link has expired.';

    public function handle(): int
    {
        $removed = PendingSignup::prune();

        $this->info("Pruned {$removed} expired pending sign-up(s).");

        return self::SUCCESS;
    }
}
