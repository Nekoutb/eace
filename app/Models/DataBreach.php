<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Carbon;

/**
 * Central. A recorded personal-data breach (Loi 2024/017 art. 22).
 *
 * @property int $id
 * @property Carbon $detected_at
 * @property string $description
 * @property ?string $categories
 * @property ?string $volume
 * @property string $risk
 * @property ?string $measures
 * @property ?Carbon $authority_notified_at
 * @property ?Carbon $subjects_notified_at
 * @property string $status
 * @property ?string $reported_by
 */
class DataBreach extends CentralModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'authority_notified_at' => 'datetime',
            'subjects_notified_at' => 'datetime',
        ];
    }
}
