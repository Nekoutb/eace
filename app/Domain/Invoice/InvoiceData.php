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
        public ?string $invoiceNumber = null,
        public ?string $supplierName = null,
        public ?string $supplierAddress = null,
        public ?string $supplierRccm = null,
        public ?string $clientIdentity = null,
        public ?string $clientAddress = null,
        public ?string $clientRccm = null,
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
     * Rebuild from the array produced by toArray() — lets a scanned invoice be
     * cached on the page (round-tripped through JSON) and reused at run time
     * without a second OCR call. rawText is not round-tripped.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $str = static fn (string $k): ?string => isset($data[$k]) && is_scalar($data[$k]) ? trim((string) $data[$k]) : null;
        $int = static fn (string $k): ?int => isset($data[$k]) && is_numeric($data[$k]) ? (int) $data[$k] : null;

        $supplierNiu = $str('supplier_niu');
        $clientNiu = $str('client_niu');
        $date = $str('date');
        $exemption = $str('exemption');

        return new self(
            supplierNiu: $supplierNiu !== null ? NiuNumber::tryFrom($supplierNiu) : null,
            clientNiu: $clientNiu !== null ? NiuNumber::tryFrom($clientNiu) : null,
            date: $date !== null ? CarbonImmutable::parse($date)->startOfDay() : null,
            invoiceNumber: $str('invoice_number'),
            supplierName: $str('supplier_name'),
            supplierAddress: $str('supplier_address'),
            supplierRccm: $str('supplier_rccm'),
            clientIdentity: $str('client_identity'),
            clientAddress: $str('client_address'),
            clientRccm: $str('client_rccm'),
            description: $str('description'),
            htAmount: $int('ht_amount'),
            vatRate: isset($data['vat_rate']) && is_numeric($data['vat_rate']) ? (float) $data['vat_rate'] : null,
            vatAmount: $int('vat_amount'),
            ttcAmount: $int('ttc_amount'),
            exemption: $exemption !== null ? ExemptionMention::tryFrom($exemption) : null,
        );
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
            'invoice_number' => $this->invoiceNumber,
            'supplier_name' => $this->supplierName,
            'supplier_address' => $this->supplierAddress,
            'supplier_rccm' => $this->supplierRccm,
            'client_identity' => $this->clientIdentity,
            'client_address' => $this->clientAddress,
            'client_rccm' => $this->clientRccm,
            'description' => $this->description,
            'ht_amount' => $this->htAmount,
            'vat_rate' => $this->vatRate,
            'vat_amount' => $this->vatAmount,
            'ttc_amount' => $this->ttcAmount,
            'exemption' => $this->exemption?->value,
        ];
    }
}
