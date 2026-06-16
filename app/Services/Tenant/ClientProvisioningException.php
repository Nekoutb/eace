<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use RuntimeException;

/** A client could not be provisioned (e.g. the subdomain is already taken). */
final class ClientProvisioningException extends RuntimeException
{
    public static function subdomainTaken(string $subdomain): self
    {
        return new self("The subdomain \"{$subdomain}\" is already in use.");
    }

    public static function invalidSubdomain(string $subdomain): self
    {
        return new self("\"{$subdomain}\" is not a valid subdomain.");
    }
}
