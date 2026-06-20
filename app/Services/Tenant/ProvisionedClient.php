<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant;

/**
 * The result of provisioning a client: the new tenant (isolated workspace), the
 * shared sign-in URL the client logs in at (no per-client subdomain), and the
 * first admin's one-time credentials. The temporary password exists only here
 * and in the hashed user row — it is shown to the provisioner once and never
 * stored in plain text, so it cannot be recovered later (reset instead).
 */
final class ProvisionedClient
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $loginUrl,
        public readonly string $adminEmail,
        // Set only for admin-provisioned clients (the one-time password shown
        // once); null for self-service signups where the user chose their own.
        public readonly ?string $temporaryPassword = null,
    ) {}
}
