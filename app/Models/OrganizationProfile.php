<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-owned singleton: the organisation's own identity as the buyer. Its
 * name + NIU are what each scanned supplier invoice is checked against (the
 * client mentions of CGI art. 150).
 *
 * @property ?string $entity_name
 * @property ?string $entity_niu
 * @property ?string $entity_rccm
 * @property ?string $entity_address
 */
class OrganizationProfile extends Model
{
    protected $table = 'organization_profile';

    protected $guarded = ['id'];

    /**
     * The single profile row for the current tenant (created empty on first use).
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
