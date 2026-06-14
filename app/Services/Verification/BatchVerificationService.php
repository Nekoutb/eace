<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Models\Verification;
use Illuminate\Support\Collection;

/**
 * Runs a batch of NIUs through the verification service — the settlement-stage
 * flow, where finance pays many invoices at once. Parses free-text input,
 * de-duplicates, verifies each, and summarises accept/reject counts + exposure.
 */
final class BatchVerificationService
{
    public function __construct(private VerificationService $service) {}

    /**
     * Split free-text (one NIU per line, or comma/semicolon separated) into a
     * clean, de-duplicated, upper-cased list.
     *
     * @return array<int, string>
     */
    public function parse(string $raw): array
    {
        return collect(preg_split('/[\r\n,;]+/', $raw) ?: [])
            ->map(fn (string $n): string => strtoupper(trim($n)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Verify each NIU at the given stage (settlement by default).
     *
     * @param  array<int, string>  $nius
     * @return Collection<int, Verification>
     */
    public function verifyBatch(
        array $nius,
        VerificationStage $stage = VerificationStage::Settlement,
        ?int $checkedBy = null,
    ): Collection {
        return collect($nius)->map(fn (string $niu): Verification => $this->service->verify(
            new VerifyRequest(rawInput: $niu, stage: $stage, checkedBy: $checkedBy),
        ));
    }

    /**
     * Accept/reject counts and total FCFA exposure for a set of verifications.
     *
     * @param  Collection<int, Verification>  $verifications
     * @return array{total: int, accepted: int, rejected: int, exposure_xaf: int}
     */
    public function summarise(Collection $verifications): array
    {
        return [
            'total' => $verifications->count(),
            'accepted' => $verifications->where('approval', ApprovalDecision::Accepted)->count(),
            'rejected' => $verifications->where('approval', ApprovalDecision::Rejected)->count(),
            'exposure_xaf' => (int) $verifications->sum('exposure_xaf'),
        ];
    }
}
