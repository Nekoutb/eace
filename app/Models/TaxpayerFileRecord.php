<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Central. One taxpayer row from the ingested DGI file — the offline source the
 * NIU-existence check reads.
 *
 * @property string $niu
 * @property ?string $raison_sociale
 * @property ?string $sigle
 * @property ?string $activite
 * @property ?string $regime
 * @property ?string $cri
 * @property ?string $centre
 * @property ?int $taxpayer_file_extraction_id
 */
class TaxpayerFileRecord extends CentralModel
{
    protected $table = 'taxpayer_file_records';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<TaxpayerFileExtraction, $this>
     */
    public function extraction(): BelongsTo
    {
        return $this->belongsTo(TaxpayerFileExtraction::class, 'taxpayer_file_extraction_id');
    }
}
