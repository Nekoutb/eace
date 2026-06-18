<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Domain\Invoice\ExemptionMention;
use App\Domain\Invoice\InvoiceData;
use App\Domain\Invoice\InvoiceReader;
use App\Domain\Niu\NiuNumber;
use Carbon\CarbonImmutable;

/**
 * Stand-in for the Azure reader until the cloud OCR key is configured. Returns a
 * representative, fully art.-150-conform invoice so the recording flow and the
 * conformity checks are demoable end to end without OCR. Overridable in tests.
 */
final class FakeInvoiceReader implements InvoiceReader
{
    public function __construct(private ?InvoiceData $canned = null) {}

    public function read(string $diskPath, string $disk = 'local'): InvoiceData
    {
        return $this->canned ?? new InvoiceData(
            supplierNiu: NiuNumber::fromString('M058712345678X'),
            clientNiu: NiuNumber::fromString('M020100021296Z'),
            date: CarbonImmutable::now()->subDays(3),
            invoiceNumber: 'FAC-2026-0042',
            supplierName: 'SARL EXEMPLE',
            supplierAddress: 'BP 1234, Douala',
            supplierRccm: 'RC/DLA/2020/B/123',
            clientIdentity: 'EXPRESS UNION FINANCE SA',
            clientAddress: 'BP 5000, Douala',
            clientRccm: 'RC/DLA/2015/B/777',
            description: 'Prestation de services',
            htAmount: 1_000_000,
            vatRate: 0.1925,
            vatAmount: 192_500,
            ttcAmount: 1_192_500,
            exemption: ExemptionMention::None,
        );
    }
}
