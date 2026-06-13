<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Niu\NiuEntityType;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The shared cache of the latest known state of each NIU (central DB).
 *
 * @property string $niu
 * @property ?NiuEntityType $entity_type
 * @property ?string $taxpayer_name
 * @property ?string $fiscalis_status
 * @property ?bool $on_active_file
 * @property ?Carbon $last_checked_at
 */
class NiuRegistry extends CentralModel
{
    protected $table = 'niu_registry';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'entity_type' => NiuEntityType::class,
            'on_active_file' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<NiuRegistrySnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(NiuRegistrySnapshot::class, 'niu', 'niu');
    }
}
