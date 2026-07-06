<?php

declare(strict_types=1);

namespace App\Domain\Firm;

/**
 * A person's role WITHIN their accounting firm (distinct from their per-account
 * role inside a client workspace, which is App\Domain\Auth\UserRole):
 * - power_user manages the firm — creates client sub-accounts, invites members,
 *   assigns who works which account.
 * - member works only the accounts assigned to them; no firm management.
 */
enum FirmRole: string
{
    case PowerUser = 'power_user';
    case Member = 'member';

    public function label(): string
    {
        return (string) __('firm.role.'.$this->value);
    }

    public function isPowerUser(): bool
    {
        return $this === self::PowerUser;
    }
}
