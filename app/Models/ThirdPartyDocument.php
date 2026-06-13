<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\ThirdParty\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tenant-owned. A compliance document attached to a counterparty.
 *
 * @property DocumentType $type
 * @property ?Carbon $issued_at
 * @property ?Carbon $expires_at
 */
class ThirdPartyDocument extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'issued_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    /**
     * True when the document has an expiry that is in the past.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @return BelongsTo<ThirdParty, $this>
     */
    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }
}
