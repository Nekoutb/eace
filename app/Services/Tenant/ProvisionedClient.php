<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant;

/**
 * The result of provisioning a client: the new tenant, the subdomain it lives
 * at, and the first admin's one-time credentials. The temporary password exists
 * only here and in the hashed user row — it is shown to the provisioner once and
 * never stored in plain text, so it cannot be recovered later (reset instead).
 */
final class ProvisionedClient
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $domain,
        public readonly string $adminEmail,
        public readonly string $temporaryPassword,
    ) {}
}
