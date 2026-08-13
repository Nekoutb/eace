<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Domain\Fiscalis\FiscalisPresence;
use App\Domain\Niu\Verdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tenant-owned. The record of one NIU verification: the stage it ran at, the
 * four-check approval (accept/reject) and its individual checks, the graded
 * verdict + FCFA exposure, and a pointer to the central evidence snapshot.
 *
 * @property string $raw_input
 * @property ?string $niu
 * @property ?string $vendor_name
 * @property VerificationStage $stage
 * @property ?string $snapshot_id
 * @property Verdict $verdict
 * @property ?ApprovalDecision $approval
 * @property array<int, array<string, mixed>>|null $checks
 * @property array<string, mixed>|null $invoice_data
 * @property array<int, array<string, mixed>>|null $invoice_checks
 * @property array<int, array<string, mixed>>|null $triggered_rules
 * @property int $exposure_xaf
 * @property ?string $invoice_file_path
 * @property array<string, mixed>|null $manifest
 * @property ?string $override_reason
 * @property ?int $overridden_by
 * @property ?Carbon $overridden_at
 * @property ?Carbon $archived_at
 * @property ?FiscalisPresence $fiscalis_presence
 * @property ?string $fiscalis_snapshot_path
 * @property ?Carbon $fiscalis_checked_at
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
            'invoice_data' => 'array',
            'invoice_checks' => 'array',
            'triggered_rules' => 'array',
            'exposure_xaf' => 'integer',
            'manifest' => 'array',
            'overridden_at' => 'datetime',
            'archived_at' => 'datetime',
            'fiscalis_presence' => FiscalisPresence::class,
            'fiscalis_checked_at' => 'datetime',
        ];
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @return BelongsTo<AcfCertificate, $this>
     */
    public function acfCertificate(): BelongsTo
    {
        return $this->belongsTo(AcfCertificate::class);
    }

    /**
     * The operator who overrode the system's rejection to proceed, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    /** Whether an operator has overridden this rejection to proceed. */
    public function isOverridden(): bool
    {
        return $this->overridden_at !== null;
    }

    private ?NiuRegistrySnapshot $snapshotCache = null;

    private bool $snapshotLoaded = false;

    /**
     * The immutable central snapshot this verification was based on — the
     * evidence (fiscalis extract) behind its checks. Memoised per instance.
     */
    public function snapshot(): ?NiuRegistrySnapshot
    {
        if (! $this->snapshotLoaded) {
            $this->snapshotCache = $this->snapshot_id === null
                ? null
                : NiuRegistrySnapshot::find($this->snapshot_id);
            $this->snapshotLoaded = true;
        }

        return $this->snapshotCache;
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
