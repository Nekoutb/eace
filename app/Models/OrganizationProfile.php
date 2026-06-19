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
 * @property ?array<int, string> $entity_name_aliases
 * @property ?string $entity_niu
 * @property ?string $entity_rccm
 * @property ?string $entity_address
 */
class OrganizationProfile extends Model
{
    protected $table = 'organization_profile';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['entity_name_aliases' => 'array'];
    }

    /**
     * The single profile row for the current tenant (created empty on first use).
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * Every accepted spelling of the buyer's own name: the primary entity name
     * plus any admin-entered variations, trimmed and de-duplicated.
     *
     * @return array<int, string>
     */
    public function acceptedNames(): array
    {
        $names = array_merge(
            [$this->entity_name],
            is_array($this->entity_name_aliases) ? $this->entity_name_aliases : [],
        );

        return array_values(array_unique(array_filter(
            array_map(static fn ($n): string => trim((string) $n), $names),
            static fn (string $n): bool => $n !== '',
        )));
    }
}
