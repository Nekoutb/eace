<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-owned. An evidence artefact (diligence PDF, screenshot, raw HTML)
 * tied to a verification, with a SHA-256 hash chained to the previous one.
 *
 * @property string $kind
 * @property string $path
 * @property string $content_hash
 * @property ?string $previous_hash
 */
class EvidenceFile extends Model
{
    public const UPDATED_AT = null; // evidence is immutable once written

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Verification, $this>
     */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class);
    }
}
