<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

use App\Domain\Niu\NiuNumber;
use Carbon\CarbonImmutable;

/**
 * The fields an invoice must carry under CGI art. 150. Any field may be null
 * when extraction could not read it — the conformity checker then flags the
 * missing mention (condition de forme). Amounts are whole FCFA (XAF, no decimals).
 */
final readonly class InvoiceData
{
    public function __construct(
        public ?NiuNumber $supplierNiu = null,
        public ?NiuNumber $clientNiu = null,
        public ?CarbonImmutable $date = null,
        public ?string $supplierName = null,
        public ?string $supplierAddress = null,
        public ?string $supplierRccm = null,
        public ?string $clientIdentity = null,
        public ?string $description = null,
        public ?int $htAmount = null,
        public ?float $vatRate = null,
        public ?int $vatAmount = null,
        public ?int $ttcAmount = null,
        public ?ExemptionMention $exemption = null,
        public ?string $rawText = null,
    ) {}

    public function isExempt(): bool
    {
        return $this->exemption !== null && $this->exemption !== ExemptionMention::None;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'supplier_niu' => $this->supplierNiu?->value,
            'client_niu' => $this->clientNiu?->value,
            'date' => $this->date?->toDateString(),
            'supplier_name' => $this->supplierName,
            'supplier_address' => $this->supplierAddress,
            'supplier_rccm' => $this->supplierRccm,
            'client_identity' => $this->clientIdentity,
            'description' => $this->description,
            'ht_amount' => $this->htAmount,
            'vat_rate' => $this->vatRate,
            'vat_amount' => $this->vatAmount,
            'ttc_amount' => $this->ttcAmount,
            'exemption' => $this->exemption?->value,
        ];
    }
}
