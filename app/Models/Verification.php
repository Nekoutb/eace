<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Niu\Verdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-owned. The record of one NIU verification: its verdict, the rules it
 * triggered (each with a CGI article and an estimated FCFA exposure), and a
 * pointer to the central evidence snapshot.
 *
 * @property string $raw_input
 * @property ?string $niu
 * @property ?string $snapshot_id
 * @property Verdict $verdict
 * @property array<int, array<string, mixed>>|null $triggered_rules
 * @property int $exposure_xaf
 */
class Verification extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'verdict' => Verdict::class,
            'triggered_rules' => 'array',
            'exposure_xaf' => 'integer',
        ];
    }

    /**
     * The immutable central snapshot this verification was based on.
     */
    public function snapshot(): ?NiuRegistrySnapshot
    {
        return $this->snapshot_id === null
            ? null
            : NiuRegistrySnapshot::find($this->snapshot_id);
    }

    /**
     * @return BelongsTo<ThirdParty, $this>
     */
    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    /**
     * @return HasMany<EvidenceFile, $this>
     */
    public function evidenceFiles(): HasMany
    {
        return $this->hasMany(EvidenceFile::class);
    }
}
