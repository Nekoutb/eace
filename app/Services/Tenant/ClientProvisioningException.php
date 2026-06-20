<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use RuntimeException;

/** A client could not be provisioned (e.g. the admin email is already in use). */
final class ClientProvisioningException extends RuntimeException
{
    public static function emailTaken(string $email): self
    {
        return new self("The email \"{$email}\" is already in use by another workspace.");
    }
}
