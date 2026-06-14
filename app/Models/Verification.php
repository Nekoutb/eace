<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Domain\Niu\Verdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-owned. The record of one NIU verification: the stage it ran at, the
 * four-check approval (accept/reject) and its individual checks, the graded
 * verdict + FCFA exposure, and a pointer to the central evidence snapshot.
 *
 * @property string $raw_input
 * @property ?string $niu
 * @property VerificationStage $stage
 * @property ?string $snapshot_id
 * @property Verdict $verdict
 * @property ?ApprovalDecision $approval
 * @property array<int, array<string, mixed>>|null $checks
 * @property array<int, array<string, mixed>>|null $triggered_rules
 * @property int $exposure_xaf
 */
class Verification extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stage' => VerificationStage::class,
            'verdict' => Verdict::class,
            'approval' => ApprovalDecision::class,
            'checks' => 'array',
            'triggered_rules' => 'array',
            'exposure_xaf' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AcfCertificate, $this>
     */
    public function acfCertificate(): BelongsTo
    {
        return $this->belongsTo(AcfCertificate::class);
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
