<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Mail\WorkspaceCredentialsMail;
use App\Models\DataAccessLog;
use App\Models\FirmMember;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Firm\FirmMembershipService;
use App\Support\TempPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Operator "the client forgot their password" reset, from the admin console:
 * generates a one-time password, forces a change on first sign-in, emails the
 * credentials to the user (best-effort — the operator also sees the password once
 * to pass it on), and audits the act.
 *
 * Two branches: a FIRM MEMBER's password lives on their central identity and is
 * fanned out to every workspace they hold; a standalone workspace user's password
 * lives in that one tenant database.
 */
final class ClientPasswordReset
{
    public function __construct(private FirmMembershipService $firms) {}

    /**
     * The choices the operator can reset for this workspace: its users' emails,
     * labelled with the person's name.
     *
     * @return array<string, string> email => "Name (email)"
     */
    public function resettableUsers(Tenant $tenant): array
    {
        /** @var array<string, string> */
        return $tenant->run(function (): array {
            $out = [];
            foreach (User::query()->orderBy('name')->get() as $user) {
                $out[(string) $user->email] = $user->name.' ('.$user->email.')';
            }

            return $out;
        });
    }

    /** Reset one user's password; returns the one-time password to show ONCE. */
    public function reset(Tenant $tenant, string $email): string
    {
        $temporary = TempPassword::generate();

        $member = FirmMember::query()->where('email', $email)->first();

        if ($member !== null) {
            // Central identity: update once, fan out to every workspace they hold.
            $member->forceFill(['password' => $temporary, 'must_change_password' => true])->save();
            $this->firms->syncCredentials($member);
            $displayName = $member->name;
        } else {
            $displayName = (string) $tenant->run(function () use ($email, $temporary): string {
                $user = User::query()->where('email', $email)->firstOrFail();
                $user->forceFill(['password' => Hash::make($temporary), 'must_change_password' => true])->save();

                return (string) $user->name;
            });
        }

        DataAccessLog::record('password_reset', 'tenant:'.$tenant->getTenantKey(), 'user '.$email.' by admin '.(string) auth()->user()?->getAttribute('email'));

        // Best-effort email — the operator still sees the password once either way.
        try {
            Mail::to($email)->locale(app()->getLocale())->send(new WorkspaceCredentialsMail(
                name: $displayName,
                orgName: (string) ($tenant->name ?? $tenant->getTenantKey()),
                email: $email,
                temporaryPassword: $temporary,
                loginUrl: rtrim((string) config('app.url'), '/').'/app/login',
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        return $temporary;
    }
}
