<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Auth\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Recovery hatch: promote a tenant user to administrator from the CLI. Exists so
 * that a client which somehow ends up with no administrator (the in-app guards
 * make this very hard) can be recovered without hand-editing the database.
 */
class PromoteTenantUser extends Command
{
    protected $signature = 'tenant:promote {tenant : The tenant id} {email : The user email to promote}';

    protected $description = 'Promote a tenant user to administrator (recovery if a client loses all admins)';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if ($tenant === null) {
            $this->error("Tenant \"{$this->argument('tenant')}\" not found.");

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');

        $promoted = $tenant->run(function () use ($email): bool {
            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                return false;
            }
            $user->update(['role' => UserRole::Admin]);

            return true;
        });

        if (! $promoted) {
            $this->error("No user with email \"{$email}\" in tenant \"{$tenant->id}\".");

            return self::FAILURE;
        }

        $this->info("Promoted {$email} to administrator in tenant \"{$tenant->id}\".");

        return self::SUCCESS;
    }
}
