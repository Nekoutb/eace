<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Central. A self-service sign-up awaiting email confirmation. The workspace is
 * provisioned only when a valid (unexpired) token is consumed — see
 * App\Services\Tenant\SelfServiceOnboarding.
 *
 * @property string $token
 * @property string $org_name
 * @property string $name
 * @property string $email
 * @property Carbon $expires_at
 */
class PendingSignup extends CentralModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** Begin a pending sign-up, returning the row carrying the verification token. */
    public static function start(string $orgName, string $name, string $email): self
    {
        // A fresh request for the same email supersedes any earlier pending one.
        static::query()->where('email', $email)->delete();

        return static::query()->create([
            'token' => Str::random(64),
            'org_name' => trim($orgName),
            'name' => trim($name),
            'email' => $email,
            'expires_at' => now()->addHours((int) config('niu.pending_signup_expiry_hours', 24)),
        ]);
    }

    /**
     * Atomically consume a still-valid token: the row is removed and returned, so
     * the same link can never provision twice. Returns null if the token is
     * unknown, already used, or expired.
     */
    public static function consumeValidByToken(string $token): ?self
    {
        $pending = static::query()
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if ($pending === null) {
            return null;
        }

        $pending->delete();

        return $pending;
    }

    /**
     * Whether this email can begin a sign-up: not already a provisioned workspace
     * (the central login directory) and no other live pending request.
     */
    public static function emailAvailable(string $email): bool
    {
        if (TenantLogin::tenantFor($email) !== null) {
            return false;
        }

        return ! static::query()
            ->where('email', $email)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /** Drop expired pending sign-ups. Returns how many were removed. */
    public static function prune(): int
    {
        return static::query()->where('expires_at', '<=', now())->delete();
    }
}
