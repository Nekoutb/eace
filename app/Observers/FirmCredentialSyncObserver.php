<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\FirmMember;
use App\Models\User;
use App\Services\Firm\FirmMembershipService;

/**
 * Phase 1c of the accounting-firm umbrella: when a firm member changes their
 * password (or enrols / re-enrols their authenticator) INSIDE any workspace —
 * first-login temp-password change, an in-app change, a reset — the change is
 * pushed back to their central FirmMember identity and fanned out to every other
 * workspace they hold. One person, one password, one authenticator entry, always.
 *
 * No-op for standalone clients (their email is not a firm member) and for central
 * users. A static guard stops the fan-out (which saves sibling user rows inside
 * other tenants) from re-triggering itself.
 */
final class FirmCredentialSyncObserver
{
    private static bool $syncing = false;

    public function saved(User $user): void
    {
        if (self::$syncing || tenant() === null || $user->isCentralUser()) {
            return;
        }

        if (! $user->wasChanged(['password', 'app_authentication_secret', 'app_authentication_recovery_codes', 'must_change_password'])) {
            return;
        }

        $member = FirmMember::query()->where('email', $user->email)->first();
        if ($member === null) {
            return;
        }

        self::$syncing = true;

        try {
            // Copy the STORED hash verbatim (the hashed cast skips re-hashing) and
            // the decrypted secret/codes (re-encrypted by the member's casts).
            $member->forceFill([
                'password' => (string) ($user->getAttributes()['password'] ?? ''),
                'app_authentication_secret' => $user->app_authentication_secret,
                'app_authentication_recovery_codes' => $user->app_authentication_recovery_codes,
                'must_change_password' => (bool) ($user->must_change_password ?? false),
            ])->save();

            app(FirmMembershipService::class)->syncCredentials($member);
        } finally {
            self::$syncing = false;
        }
    }
}
