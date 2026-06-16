<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A client. Custom attributes (e.g. name) are stored in the stancl `data` JSON
 * column via VirtualColumn, so `$tenant->name` reads and writes transparently.
 *
 * @property ?string $name
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
}
