<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A client. Custom attributes (e.g. name, allocated_space_mb) are stored in the
 * stancl `data` JSON column via VirtualColumn, so `$tenant->name` reads and
 * writes transparently.
 *
 * @property ?string $name
 * @property ?int $allocated_space_mb
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /** The tenant's primary subdomain (e.g. acme.localhost), or null if none. */
    public function primaryDomain(): ?string
    {
        $domain = $this->domains()->first();

        return $domain === null ? null : (string) $domain->getAttribute('domain');
    }

    /** The evidence storage quota for this workspace, in megabytes. */
    public function allocatedSpaceMb(): int
    {
        $value = $this->allocated_space_mb;

        return $value !== null && (int) $value > 0
            ? (int) $value
            : (int) config('niu.default_allocated_space_mb', 1024);
    }
}
