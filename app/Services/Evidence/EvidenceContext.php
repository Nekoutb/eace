<?php

declare(strict_types=1);

namespace App\Services\Evidence;

use App\Domain\Approval\VerificationStage;
use App\Models\AcfCertificate;
use App\Models\Verification;
use Carbon\CarbonImmutable;

/**
 * The addressing context for one piece of compliance evidence — everything
 * EvidenceStore needs to file it under the human-browsable scheme
 *   <tenant>/<year>/suppliers/<NIU__Vendor>/<leaf>/<descriptive file>
 * where <leaf> is the invoice reference (recording) or settlements/<date>
 * (settlement). Built from whatever record is on hand at the storage site — a
 * Verification (full per-invoice context) or an ACF certificate (vendor-level,
 * filed under acf-<reference> because one ACF covers several of a vendor's
 * invoices). The year is the year the control was performed (the audit unit).
 */
final class EvidenceContext
{
    public function __construct(
        public readonly string $phase,        // 'recording' | 'settlement'
        public readonly ?string $vendorNiu,
        public readonly ?string $vendorName,
        public readonly string $leaf,         // 'FAC-2026-001' | 'acf-620123456' | 'settlements/2026-06-25'
        public readonly CarbonImmutable $performedAt,
    ) {}

    public static function make(string $phase, ?string $vendorNiu, ?string $vendorName, string $leaf, ?CarbonImmutable $at = null): self
    {
        return new self($phase, $vendorNiu, $vendorName, $leaf, $at ?? CarbonImmutable::now());
    }

    /** Per-invoice (recording) or per-payment-date (settlement) context from a persisted verification. */
    public static function forVerification(Verification $verification, ?CarbonImmutable $at = null): self
    {
        $at ??= CarbonImmutable::now();
        $data = is_array($verification->invoice_data) ? $verification->invoice_data : [];
        $isSettlement = $verification->stage === VerificationStage::Settlement;

        $niu = $verification->niu
            ?? (is_string($data['supplier_niu'] ?? null) ? $data['supplier_niu'] : null)
            ?? $verification->raw_input;

        $invoiceRef = is_string($data['invoice_number'] ?? null) && trim($data['invoice_number']) !== ''
            ? trim($data['invoice_number'])
            : 'invoice-'.$verification->getKey();

        return new self(
            $isSettlement ? 'settlement' : 'recording',
            $niu,
            is_string($data['supplier_name'] ?? null) ? $data['supplier_name'] : null,
            $isSettlement ? 'settlements/'.$at->format('Y-m-d') : $invoiceRef,
            $at,
        );
    }

    /** Vendor-level context for an ACF certificate (filed under acf-<reference>). */
    public static function forAcf(AcfCertificate $acf, ?CarbonImmutable $at = null): self
    {
        return new self(
            'recording',
            $acf->niu,
            null,
            'acf-'.EvidenceStore::sanitize((string) $acf->reference, 'ref'),
            $at ?? CarbonImmutable::now(),
        );
    }

    public function year(): string
    {
        return $this->performedAt->format('Y');
    }

    /** Sortable date+time stamp for filenames: 2026-06-21_142530 (14h25m30s). */
    public function stamp(): string
    {
        return $this->performedAt->format('Y-m-d_His');
    }

    /** The vendor folder name: NIU__Sanitised_Vendor_Name, or the NIU alone, or a stable fallback. */
    public function supplierSegment(): string
    {
        $niu = EvidenceStore::sanitize($this->vendorNiu);
        $name = EvidenceStore::sanitize($this->vendorName);

        if ($niu !== '' && $name !== '') {
            return $niu.'__'.$name;
        }

        return $niu !== '' ? $niu : ($name !== '' ? $name : 'unknown-vendor');
    }
}
