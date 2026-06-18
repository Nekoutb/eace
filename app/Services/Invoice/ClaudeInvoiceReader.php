<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Domain\Invoice\InvoiceData;
use App\Domain\Invoice\InvoiceReader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Reads an invoice with Anthropic's Claude (vision / PDF). The document is sent
 * to the Messages API with a forced `extract_invoice` tool whose schema is the
 * CGI art. 150 fields, so Claude returns structured JSON we map straight into
 * InvoiceData. The API key comes from config — never handled in code. The
 * mapping (mapResult) is pure and unit-tested; the HTTP call is thin around it.
 */
final class ClaudeInvoiceReader implements InvoiceReader
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    public function __construct(
        private string $apiKey,
        private string $model,
    ) {}

    public function read(string $diskPath, string $disk = 'local'): InvoiceData
    {
        $bytes = Storage::disk($disk)->get($diskPath);

        if ($bytes === null) {
            throw new RuntimeException("Invoice file not found: {$diskPath}");
        }

        return $this->mapResult($this->extract($bytes, $this->mediaType($diskPath)));
    }

    /**
     * Map the extract_invoice tool input into InvoiceData. Pure — unit-tested.
     * The tool schema deliberately mirrors InvoiceData::toArray().
     *
     * @param  array<string, mixed>  $toolInput
     */
    public function mapResult(array $toolInput): InvoiceData
    {
        return InvoiceData::fromArray($toolInput);
    }

    /**
     * @return array<string, mixed> the extract_invoice tool input
     */
    private function extract(string $bytes, string $mediaType): array
    {
        $source = ['type' => 'base64', 'media_type' => $mediaType, 'data' => base64_encode($bytes)];

        $documentBlock = $mediaType === 'application/pdf'
            ? ['type' => 'document', 'source' => $source]
            : ['type' => 'image', 'source' => $source];

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::VERSION,
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::ENDPOINT, [
            'model' => $this->model,
            'max_tokens' => 1024,
            'tools' => [$this->tool()],
            'tool_choice' => ['type' => 'tool', 'name' => 'extract_invoice'],
            'messages' => [[
                'role' => 'user',
                'content' => [
                    $documentBlock,
                    ['type' => 'text', 'text' => $this->instructions()],
                ],
            ]],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Claude invoice extraction failed: HTTP '.$response->status());
        }

        foreach ((array) $response->json('content', []) as $block) {
            if (is_array($block)
                && ($block['type'] ?? null) === 'tool_use'
                && ($block['name'] ?? null) === 'extract_invoice'
                && is_array($block['input'] ?? null)
            ) {
                /** @var array<string, mixed> $input */
                $input = $block['input'];

                return $input;
            }
        }

        throw new RuntimeException('Claude returned no structured invoice data.');
    }

    private function mediaType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/pdf',
        };
    }

    private function instructions(): string
    {
        return <<<'TXT'
            You are reading a Cameroon supplier invoice. Extract the CGI art. 150
            fields and return them by calling the extract_invoice tool. Rules:
            - Amounts (ht_amount, vat_amount, ttc_amount) are WHOLE FCFA integers —
              no decimals, no thousands separators.
            - vat_rate is the VAT rate as a decimal fraction, e.g. 0.1925 for 19.25%.
            - date is the invoice date as YYYY-MM-DD.
            - supplier_niu / client_niu are 14-char NIUs (P or M + 12 digits + a letter).
            - exemption: 'exonere' if the invoice is marked « Exonérée »,
              'prise_en_charge_etat' if « prise en charge État », otherwise 'none'.
            - Omit any field that does not appear on the invoice.
            TXT;
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(): array
    {
        return [
            'name' => 'extract_invoice',
            'description' => 'Return the structured CGI art. 150 fields read off the invoice.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'supplier_niu' => ['type' => 'string', 'description' => 'Supplier NIU (14 chars)'],
                    'client_niu' => ['type' => 'string', 'description' => 'Client/buyer NIU (14 chars)'],
                    'date' => ['type' => 'string', 'description' => 'Invoice date, YYYY-MM-DD'],
                    'invoice_number' => ['type' => 'string', 'description' => 'Invoice number / reference printed on the invoice'],
                    'supplier_name' => ['type' => 'string', 'description' => 'Supplier business name'],
                    'supplier_address' => ['type' => 'string', 'description' => 'Supplier full address'],
                    'supplier_rccm' => ['type' => 'string', 'description' => 'Supplier RCCM number'],
                    'client_identity' => ['type' => 'string', 'description' => 'Client full identity/name'],
                    'client_address' => ['type' => 'string', 'description' => 'Client (buyer) full billing address'],
                    'client_rccm' => ['type' => 'string', 'description' => 'Client (buyer) RCCM / trade-register number, if shown'],
                    'description' => ['type' => 'string', 'description' => 'Nature/object of the operation'],
                    'ht_amount' => ['type' => 'integer', 'description' => 'Total excl. tax, whole FCFA'],
                    'vat_rate' => ['type' => 'number', 'description' => 'VAT rate as a fraction (0.1925)'],
                    'vat_amount' => ['type' => 'integer', 'description' => 'VAT amount, whole FCFA'],
                    'ttc_amount' => ['type' => 'integer', 'description' => 'Total incl. tax, whole FCFA'],
                    'exemption' => ['type' => 'string', 'enum' => ['none', 'exonere', 'prise_en_charge_etat']],
                ],
            ],
        ];
    }
}
