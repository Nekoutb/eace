<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * On-demand refresh of the shared DGI taxpayer register, dispatched from the
 * "Update register" button so the click returns instantly and the multi-minute
 * download + ingest run on the queue worker instead of the web request.
 *
 * The register is CENTRAL (one file for every workspace), so even though the
 * button is clicked inside a tenant panel we end any restored tenant context
 * first — the command must read/write the central database and the central
 * taxpayer-files directory, never a tenant's. ShouldBeUnique + the command's own
 * cache lock keep concurrent clicks from stacking up duplicate downloads.
 */
class RefreshTaxpayerFile implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Don't keep a duplicate refresh queued while one is already pending/running. */
    public int $uniqueFor = 1800;

    public function uniqueId(): string
    {
        return 'taxpayer-file:refresh';
    }

    public function handle(): void
    {
        // The queue may have restored the dispatching tenant's context
        // (QueueTenancyBootstrapper). This is a central operation — drop it so
        // the command resolves the central DB and the shared storage path.
        if (tenant() !== null) {
            tenancy()->end();
        }

        Artisan::call('taxpayer-file:fetch');
    }
}
