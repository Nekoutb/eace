<?php

declare(strict_types=1);

namespace App\Services\Firm;

use RuntimeException;

/**
 * A firm-management operation was refused for a business reason (surfaced to the
 * power user / operator as a friendly message, never a 500).
 */
final class FirmException extends RuntimeException
{
    public static function emailTaken(string $email): self
    {
        return new self("The email {$email} already belongs to a member or a client account.");
    }

    public static function subAccountCapReached(int $cap): self
    {
        return new self("This firm's plan allows at most {$cap} client sub-accounts.");
    }

    public static function crossFirmAssignment(): self
    {
        return new self('A member can only be assigned to a sub-account of their own firm.');
    }

    public static function firmHasSubAccounts(): self
    {
        return new self('Delete or reassign this firm\'s client sub-accounts before removing the firm.');
    }
}
