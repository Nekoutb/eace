<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Central. One extraction of the DGI monthly taxpayer file — the audit reference
 * the NIU-existence check points at (which published file, captured when).
 *
 * @property string $period_label
 * @property ?string $source
 * @property ?string $source_url
 * @property ?string $file_path
 * @property ?string $file_hash
 * @property int $row_count
 * @property string $status
 * @property ?string $message
 * @property ?Carbon $extracted_at
 */
class TaxpayerFileExtraction extends CentralModel
{
    protected $table = 'taxpayer_file_extractions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'row_count' => 'integer',
            'extracted_at' => 'datetime',
        ];
    }

    /**
     * The most recent successfully-ingested extraction, or null when none yet.
     */
    public static function current(): ?self
    {
        return static::query()
            ->whereIn('status', ['completed', 'unchanged'])
            ->orderByDesc('extracted_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return HasMany<TaxpayerFileRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(TaxpayerFileRecord::class);
    }
}
