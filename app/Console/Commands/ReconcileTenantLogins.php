<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantLogin;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Rebuilds the central login directory (email → workspace) from the source of
 * truth — every tenant's users. Run once when switching an existing deployment to
 * account-based (no-subdomain) tenancy, and any time the directory needs repair.
 */
class ReconcileTenantLogins extends Command
{
    protected $signature = 'tenancy:reconcile-logins';

    protected $description = 'Rebuild the central login directory (email → workspace) from every tenant\'s users.';

    public function handle(): int
    {
        TenantLogin::query()->delete();

        $linked = 0;

        Tenant::query()->cursor()->each(function (Tenant $tenant) use (&$linked): void {
            $tenant->run(function () use ($tenant, &$linked): void {
                User::query()->get()->each(function (User $user) use ($tenant, &$linked): void {
                    if (! $user->isCentralUser() && filled($user->email)) {
                        TenantLogin::link((string) $user->email, (string) $tenant->getTenantKey());
                        $linked++;
                    }
                });
            });
        });

        $this->info("Linked {$linked} sign-in(s) to their workspaces.");

        return self::SUCCESS;
    }
}
