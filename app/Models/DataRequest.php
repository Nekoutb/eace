<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Carbon;

/**
 * Central. A data-subject request (Loi 2024/017 art. 37–46) — access, rectification,
 * erasure, objection or portability — raised by a customer and tracked to closure.
 *
 * @property int $id
 * @property ?string $tenant_id
 * @property ?string $tenant_name
 * @property string $requester_email
 * @property ?string $requester_name
 * @property string $type
 * @property ?string $details
 * @property string $status
 * @property ?Carbon $due_at
 * @property ?string $handled_by
 * @property ?Carbon $handled_at
 */
class DataRequest extends CentralModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }
}
