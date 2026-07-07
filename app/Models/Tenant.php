<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A client workspace (sub-account). Custom attributes (e.g. name,
 * allocated_space_mb) are stored in the stancl `data` JSON column via
 * VirtualColumn, so `$tenant->name` reads and writes transparently. firm_id is a
 * REAL column (see getCustomColumns) so the admin can group and roll up clients by
 * accounting firm; null firm_id = a standalone client.
 *
 * @property ?string $name
 * @property ?int $allocated_space_mb
 * @property ?int $firm_id
 * @property ?string $suspended_at
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /**
     * Real (non-JSON) columns. firm_id is added here so it is stored as its own
     * indexed column — queryable and groupable — not inside the data blob.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'firm_id'];
    }

    /**
     * The accounting firm this sub-account belongs to, or null for a standalone
     * client.
     *
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * Suspended (e.g. unpaid bills): sign-in and all use of the workspace are
     * blocked until reactivated. A firm-owned sub-account is also suspended when
     * its whole firm is deactivated. Data is retained untouched throughout.
     */
    public function isSuspended(): bool
    {
        if ($this->suspended_at !== null) {
            return true;
        }

        return $this->firm_id !== null && $this->firm()->first()?->is_active === false;
    }

    public function suspend(): void
    {
        $this->update(['suspended_at' => now()->toDateTimeString()]);
    }

    public function unsuspend(): void
    {
        $this->update(['suspended_at' => null]);
    }

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
