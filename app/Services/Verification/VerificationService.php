<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Domain\Approval\ApprovalContext;
use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\FourCheckEvaluator;
use App\Domain\Invoice\InvoiceConformityChecker;
use App\Domain\Niu\NiuNumber;
use App\Domain\Risk\RiskEngine;
use App\Domain\Risk\VerificationContext;
use App\Models\AcfCertificate;
use App\Models\OrganizationProfile;
use App\Models\ThirdParty;
use App\Models\Verification;
use App\Services\Registry\NiuRegistryService;
use App\Services\Registry\RegistryLookup;

/**
 * Orchestrates one verification end to end (tenant context): parse the NIU,
 * look it up in the central registry, resolve any ACF, run the four-check
 * approval AND the graded rule engine, fold in the invoice conditions de forme
 * et de fond when an invoice was submitted, and persist the result.
 */
final class VerificationService
{
    public function __construct(
        private NiuRegistryService $registry,
        private RiskEngine $engine,
        private FourCheckEvaluator $approval,
        private InvoiceConformityChecker $conformity,
    ) {}

    public function verify(VerifyRequest $request): Verification
    {
        $niu = NiuNumber::tryFrom($request->rawInput);

        $lookup = $niu !== null
            ? $this->registry->lookup($niu)
            : new RegistryLookup(null, null);

        $certificate = $this->resolveCertificate($request, $niu);
        $record = $lookup->record;

        $approval = $this->approval->evaluate(new ApprovalContext(
            stage: $request->stage,
            niu: $niu,
            foundInFile: $record !== null,
            fiscalisStatus: $record?->status,
            onActiveFile: $record !== null && $record->onActiveFile,
            acfStatus: $certificate?->status,
            acfAuthenticity: $certificate?->authenticity_status,
        ));

        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: $request->rawInput,
            niu: $niu,
            registry: $lookup->record,
            invoiceAmountXaf: $request->invoiceAmountXaf,
            invoiceName: $request->invoiceName,
            vatCharged: $request->vatCharged,
            paymentMethod: $request->paymentMethod,
            expectedEntityType: $request->expectedEntityType,
            publicSettlementOrExport: $request->publicSettlementOrExport,
            foreignSupplier: $request->foreignSupplier,
            hasValidAcf: $request->hasValidAcf,
        ));

        // At recording, a submitted invoice must also satisfy art. 150
        // (conditions de forme et de fond) for the invoice to be accepted —
        // including that the buyer it names is us (the organisation profile).
        $invoiceConformity = null;
        if ($request->invoiceData !== null) {
            $profile = OrganizationProfile::current();
            $expectedClientNiu = filled($profile->entity_niu) ? NiuNumber::tryFrom((string) $profile->entity_niu) : null;

            $invoiceConformity = $this->conformity->check(
                $request->invoiceData,
                $niu,
                $expectedClientNiu,
                $profile->entity_name,
                $profile->entity_rccm,
                $profile->entity_address,
                $profile->acceptedNames(),
                // The vendor's régime drives the VAT-invoicing coherence check:
                // from their ACF first, else the DGI register entry for the NIU.
                vendorRegime: $certificate->regime ?? $record?->regime,
            );
        }

        $invoiceConform = $invoiceConformity === null || $invoiceConformity->conform;

        $decision = ($approval->accepted() && $invoiceConform)
            ? ApprovalDecision::Accepted
            : ApprovalDecision::Rejected;

        $verification = Verification::query()->create([
            'third_party_id' => $request->thirdPartyId,
            'stage' => $request->stage,
            'raw_input' => $request->rawInput,
            'niu' => $niu !== null ? (string) $niu : null,
            'snapshot_id' => $lookup->snapshotId,
            'acf_certificate_id' => $certificate?->id,
            'verdict' => $result->verdict,
            'approval' => $decision,
            'checks' => $approval->checksToArray(),
            'invoice_data' => $request->invoiceData?->toArray(),
            'invoice_checks' => $invoiceConformity?->toArray(),
            'invoice_file_path' => $request->invoiceFilePath,
            'triggered_rules' => $result->findingsToArray(),
            'exposure_xaf' => $result->totalExposureXaf,
            'checked_by' => $request->checkedBy,
        ]);

        if ($request->thirdPartyId !== null) {
            ThirdParty::query()->whereKey($request->thirdPartyId)->update([
                'last_verdict' => $result->verdict->value,
                'last_verified_at' => now(),
            ]);
        }

        return $verification;
    }

    /**
     * Find the ACF for this verification. By explicit reference if given;
     * otherwise, among the NIU's certificates, prefer a currently reusable
     * (authentic + cleanly valid) one over a newer expired/unauthentic one,
     * falling back to the most recent. Validity is recomputed in memory only —
     * persisting the monitored status is the scheduled monitor's job, and writing
     * here would race the concurrent reads of batch settlement.
     */
    private function resolveCertificate(VerifyRequest $request, ?NiuNumber $niu): ?AcfCertificate
    {
        if ($request->acfReference !== null) {
            $certificate = AcfCertificate::query()->where('reference', $request->acfReference)->first();
            $certificate?->recomputeValidity();

            return $certificate;
        }

        if ($niu === null) {
            return null;
        }

        $candidates = AcfCertificate::query()
            ->where('niu', (string) $niu)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get()
            ->each
            ->recomputeValidity();

        // Stable sort keeps issued_at-desc order within each group, so this yields
        // the most recent reusable certificate, else the most recent overall.
        return $candidates
            ->sortByDesc(fn (AcfCertificate $c): int => $c->isReusableForSettlement() ? 1 : 0)
            ->first();
    }
}
