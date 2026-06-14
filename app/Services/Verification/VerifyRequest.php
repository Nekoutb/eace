<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Domain\Approval\VerificationStage;
use App\Domain\Invoice\InvoiceData;
use App\Domain\Niu\NiuEntityType;
use App\Domain\Risk\PaymentMethod;

/**
 * The input to a verification: the NIU to check plus optional transaction
 * context that lets the richer rules (cash, name, régime, ACF, foreign) fire.
 * A bare check needs only rawInput.
 */
final readonly class VerifyRequest
{
    public function __construct(
        public string $rawInput,
        public VerificationStage $stage = VerificationStage::Recording,
        public ?string $acfReference = null,
        public ?int $thirdPartyId = null,
        public ?int $invoiceAmountXaf = null,
        public ?string $invoiceName = null,
        public bool $vatCharged = false,
        public ?PaymentMethod $paymentMethod = null,
        public ?NiuEntityType $expectedEntityType = null,
        public bool $publicSettlementOrExport = false,
        public bool $foreignSupplier = false,
        public bool $hasValidAcf = false,
        public ?InvoiceData $invoiceData = null,
        public ?int $checkedBy = null,
    ) {}
}
