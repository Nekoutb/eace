<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Auth\UserRole;
use App\Http\Controllers\SurveyController;
use App\Mail\SurveyInviteMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the anonymous customer-experience survey to each workspace's
 * administrators. The link carries only an encrypted WORKSPACE token, so responses
 * segment by client but are never tied to the individual who answers. Run annually
 * (or with --tenant to target one workspace).
 */
class SendSurvey extends Command
{
    protected $signature = 'survey:send {--tenant= : limit to a single tenant id}';

    protected $description = 'Email the anonymous customer-experience survey to workspace administrators.';

    public function handle(): int
    {
        $query = Tenant::query();
        if (filled($this->option('tenant'))) {
            $query->whereKey((string) $this->option('tenant'));
        }

        $sent = 0;
        $workspaces = 0;

        $query->cursor()->each(function (Tenant $tenant) use (&$sent, &$workspaces): void {
            $workspaces++;
            $orgName = $tenant->name ?? (string) $tenant->getTenantKey();
            $link = SurveyController::linkFor((string) $tenant->getTenantKey(), $tenant->name);

            tenancy()->initialize($tenant);
            $admins = User::query()->where('role', UserRole::Admin->value)->get(['name', 'email']);
            tenancy()->end();

            foreach ($admins as $admin) {
                if (blank($admin->email)) {
                    continue;
                }
                Mail::to((string) $admin->email)->send(new SurveyInviteMail((string) $admin->name, $orgName, $link));
                $sent++;
            }
        });

        $this->info("Sent {$sent} survey invitation(s) across {$workspaces} workspace(s).");

        return self::SUCCESS;
    }
}
