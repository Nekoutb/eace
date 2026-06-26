<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a write (INSERT/UPDATE/DELETE …) is attempted against a tenant's
 * database while an admin is impersonating that workspace. Impersonation is strictly
 * read-only — this is the hard backstop beneath the per-action UI gates.
 */
final class ReadOnlyImpersonationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Write blocked: this workspace is being viewed read-only by an administrator (impersonation).');
    }
}
