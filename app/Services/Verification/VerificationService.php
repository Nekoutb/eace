<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Domain\Niu\NiuNumber;
use App\Domain\Risk\RiskEngine;
use App\Domain\Risk\VerificationContext;
use App\Models\ThirdParty;
use App\Models\Verification;
use App\Services\Registry\NiuRegistryService;
use App\Services\Registry\RegistryLookup;

/**
 * Orchestrates one verification end to end (tenant context): parse the NIU,
 * look it up in the central registry, run the rule engine, and persist a
 * Verification with its verdict, findings and FCFA exposure — pointing at the
 * immutable central snapshot it was based on.
 */
final class VerificationService
{
    public function __construct(
        private NiuRegistryService $registry,
        private RiskEngine $engine,
    ) {}

    public function verify(VerifyRequest $request): Verification
    {
        $niu = NiuNumber::tryFrom($request->rawInput);

        $lookup = $niu !== null
            ? $this->registry->lookup($niu)
            : new RegistryLookup(null, null);

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

        $verification = Verification::query()->create([
            'third_party_id' => $request->thirdPartyId,
            'raw_input' => $request->rawInput,
            'niu' => $niu !== null ? (string) $niu : null,
            'snapshot_id' => $lookup->snapshotId,
            'verdict' => $result->verdict,
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
}
