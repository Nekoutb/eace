<?php

declare(strict_types=1);

namespace App\Domain\Risk;

use App\Domain\Niu\NiuEntityType;
use App\Domain\Niu\NiuNumber;

/**
 * Everything the rules engine needs to judge one verification. The NIU is null
 * when the raw input is not a valid NIU; the registry is null when the NIU was
 * not found in the Fiscalis file. All transaction fields are optional, so a
 * bare NIU check (no invoice) still runs — the rules that need invoice context
 * simply do not fire.
 */
final readonly class VerificationContext
{
    public function __construct(
        public string $rawInput,
        public ?NiuNumber $niu = null,
        public ?RegistryRecord $registry = null,
        public ?int $invoiceAmountXaf = null,
        public ?string $invoiceName = null,
        public bool $vatCharged = false,
        public ?PaymentMethod $paymentMethod = null,
        public ?NiuEntityType $expectedEntityType = null,
        public bool $publicSettlementOrExport = false,
        public bool $foreignSupplier = false,
        public bool $hasValidAcf = false,
    ) {}

    public function hasValidNiu(): bool
    {
        return $this->niu !== null;
    }

    public function foundInFile(): bool
    {
        return $this->registry !== null;
    }
}
