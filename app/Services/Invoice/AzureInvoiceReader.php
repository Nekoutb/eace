<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Domain\Invoice\ExemptionMention;
use App\Domain\Invoice\InvoiceData;
use App\Domain\Invoice\InvoiceReader;
use App\Domain\Niu\NiuNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Reads an invoice with Azure Document Intelligence's prebuilt-invoice model,
 * which returns structured fields (vendor, tax ids, totals, tax). We map those
 * into InvoiceData; the Cameroon-specific RCCM and the exemption mention are
 * pulled from the recognised text content. Credentials come from config — never
 * handled in code. The mapping (mapResult) is pure and unit-tested against a
 * captured response; the HTTP analyze/poll is thin around it.
 */
final class AzureInvoiceReader implements InvoiceReader
{
    private const API_VERSION = '2024-11-30';

    public function __construct(
        private string $endpoint,
        private string $key,
    ) {}

    public function read(string $diskPath, string $disk = 'local'): InvoiceData
    {
        $bytes = Storage::disk($disk)->get($diskPath);

        if ($bytes === null) {
            throw new RuntimeException("Invoice file not found: {$diskPath}");
        }

        return $this->mapResult($this->analyze($bytes));
    }

    /**
     * @return array<string, mixed> the analyzeResult payload
     */
    private function analyze(string $bytes): array
    {
        $base = rtrim($this->endpoint, '/');
        $url = "{$base}/documentintelligence/documentModels/prebuilt-invoice:analyze?api-version=".self::API_VERSION;

        $start = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->key,
            'Content-Type' => 'application/octet-stream',
        ])->withBody($bytes, 'application/octet-stream')->post($url);

        $operationLocation = $start->header('Operation-Location');

        if ($start->status() !== 202 || $operationLocation === '') {
            throw new RuntimeException('Azure analyze did not start: HTTP '.$start->status());
        }

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $poll = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $this->key])->get($operationLocation);
            $status = (string) $poll->json('status');

            if ($status === 'succeeded') {
                return (array) $poll->json('analyzeResult', []);
            }

            if ($status === 'failed') {
                throw new RuntimeException('Azure analyze failed.');
            }

            usleep(1_000_000); // 1s between polls
        }

        throw new RuntimeException('Azure analyze timed out.');
    }

    /**
     * Map an Azure analyzeResult into InvoiceData. Pure — unit-tested.
     *
     * @param  array<string, mixed>  $analyzeResult
     */
    public function mapResult(array $analyzeResult): InvoiceData
    {
        $content = (string) ($analyzeResult['content'] ?? '');
        $fields = $analyzeResult['documents'][0]['fields'] ?? [];

        return new InvoiceData(
            supplierNiu: $this->niu($this->string($fields, 'VendorTaxId') ?? $this->niuFromContent($content, 'supplier')),
            clientNiu: $this->niu($this->string($fields, 'CustomerTaxId')),
            date: $this->date($this->string($fields, 'InvoiceDate')),
            invoiceNumber: $this->string($fields, 'InvoiceId'),
            supplierName: $this->string($fields, 'VendorName'),
            supplierAddress: $this->string($fields, 'VendorAddress'),
            supplierRccm: $this->rccm($content),
            clientIdentity: $this->string($fields, 'CustomerName'),
            clientAddress: $this->string($fields, 'CustomerAddress'),
            clientRccm: null, // prebuilt-invoice has no client RCCM field; use the Claude reader for it
            description: $this->string($fields, 'Items'),
            htAmount: $this->amount($fields, 'SubTotal'),
            vatRate: $this->vatRate($fields),
            vatAmount: $this->amount($fields, 'TotalTax'),
            ttcAmount: $this->amount($fields, 'InvoiceTotal'),
            exemption: $this->exemption($content),
            rawText: $content,
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function string(array $fields, string $name): ?string
    {
        $field = $fields[$name] ?? null;
        $value = $field['valueString'] ?? $field['content'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function amount(array $fields, string $name): ?int
    {
        $amount = $fields[$name]['valueCurrency']['amount'] ?? $fields[$name]['valueNumber'] ?? null;

        return is_numeric($amount) ? (int) round((float) $amount) : null;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function vatRate(array $fields): ?float
    {
        $rate = $fields['TaxDetails']['valueArray'][0]['valueObject']['Rate']['valueNumber'] ?? null;

        if (is_numeric($rate)) {
            return $rate > 1 ? (float) $rate / 100 : (float) $rate; // accept 19.25 or 0.1925
        }

        // Fall back to deriving the rate from the amounts.
        $ht = $this->amount($fields, 'SubTotal');
        $vat = $this->amount($fields, 'TotalTax');

        return ($ht !== null && $ht > 0 && $vat !== null) ? round($vat / $ht, 4) : null;
    }

    private function niu(?string $value): ?NiuNumber
    {
        return $value !== null ? NiuNumber::tryFrom($value) : null;
    }

    private function niuFromContent(string $content, string $which): ?string
    {
        return preg_match('/\b([PM]\d{12}[A-Z])\b/u', $content, $m) === 1 ? $m[1] : null;
    }

    private function rccm(string $content): ?string
    {
        return preg_match('/(RC\s?\/?\s?[A-Z]{2,4}\s?\/?\s?\d{4}\s?\/?\s?[A-Z]\s?\/?\s?\d{2,6})/iu', $content, $m) === 1
            ? trim($m[1])
            : null;
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function exemption(string $content): ExemptionMention
    {
        if (preg_match('/prise\s+en\s+charge/iu', $content) === 1) {
            return ExemptionMention::PriseEnChargeEtat;
        }

        if (preg_match('/exon[ée]r/iu', $content) === 1) {
            return ExemptionMention::Exonere;
        }

        return ExemptionMention::None;
    }
}
