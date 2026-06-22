<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Carbon;

/**
 * Central. One row per NIU ever ingested (the "ever seen" register history).
 *
 * @property string $niu
 * @property Carbon $first_seen_at
 */
class TaxpayerNiuSeen extends CentralModel
{
    protected $table = 'taxpayer_niu_seen';

    protected $primaryKey = 'niu';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime'];
    }
}
