<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Domain\Invoice\InvoiceData;
use App\Domain\Invoice\InvoiceReader;
use App\Domain\Niu\NiuNumber;
use App\Services\Anthropic\AnthropicError;
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
        // Footer safety net: the supplier's own NIU is often only in the
        // letterhead footer (« Contribuable N° … »). If the model didn't surface
        // it, recover it from the transcribed raw_text — preferring a NIU flagged
        // as the "contribuable"/supplier, and never reusing the client's NIU.
        if (blank($toolInput['supplier_niu'] ?? null)) {
            $recovered = $this->supplierNiuFromText(
                is_string($toolInput['raw_text'] ?? null) ? $toolInput['raw_text'] : null,
                is_string($toolInput['client_niu'] ?? null) ? $toolInput['client_niu'] : null,
            );

            if ($recovered !== null) {
                $toolInput['supplier_niu'] = $recovered;
            }
        }

        // A single-total invoice with no VAT (common for non-Réel vendors) shows
        // one amount that is at once the HT and the TTC — mirror it so the
        // mandatory HT mention is satisfied.
        if (blank($toolInput['ht_amount'] ?? null)
            && blank($toolInput['vat_amount'] ?? null)
            && filled($toolInput['ttc_amount'] ?? null)) {
            $toolInput['ht_amount'] = $toolInput['ttc_amount'];
        }

        return InvoiceData::fromArray($toolInput);
    }

    /**
     * Recover the supplier NIU from the transcribed page text when the model did
     * not return it. Cameroon invoices print the supplier's own NIU in the
     * footer, usually after « Contribuable » — prefer that context, otherwise
     * take the first valid NIU that is not the client's. Whitespace is collapsed
     * first because OCR often prints the NIU spaced (« P 077412281691 C »).
     */
    private function supplierNiuFromText(?string $rawText, ?string $clientNiu): ?string
    {
        if ($rawText === null || trim($rawText) === '') {
            return null;
        }

        $compact = (string) preg_replace('/\s+/', '', $rawText);
        $clientValue = $clientNiu !== null ? NiuNumber::tryFrom($clientNiu)?->value : null;

        // 1) A NIU right after « Contribuable » — the supplier's own footer label.
        if (preg_match('/contribuable.{0,12}?([PM]\d{12}[A-Z])/iu', $compact, $m) === 1) {
            $niu = NiuNumber::tryFrom($m[1]);
            if ($niu !== null && $niu->value !== $clientValue) {
                return $niu->value;
            }
        }

        // 2) Otherwise the first valid NIU on the page that is not the client's.
        if (preg_match_all('/([PM]\d{12}[A-Z])/u', $compact, $all) >= 1) {
            foreach ($all[1] as $candidate) {
                $niu = NiuNumber::tryFrom($candidate);
                if ($niu !== null && $niu->value !== $clientValue) {
                    return $niu->value;
                }
            }
        }

        return null;
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
            // Headroom for the structured fields plus the full-page raw_text.
            'max_tokens' => 2048,
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
            throw new RuntimeException('Claude invoice extraction failed: '.AnthropicError::describe($response));
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
            You are reading a Cameroon supplier invoice. Read the ENTIRE page,
            top to bottom — the letterhead header AND the FOOTER / pied de page,
            not just the central table. Extract the CGI art. 150 fields and return
            them by calling the extract_invoice tool. Rules:
            - The SUPPLIER is the business that ISSUED the invoice (its logo /
              letterhead at the top, its stamp/signature and footer at the bottom).
              The supplier's own identifiers are FREQUENTLY printed only in the
              footer: the NIU is often labelled « Contribuable N° » / « N° Contribuable »
              (that value IS supplier_niu), and the supplier's RCCM and régime often
              sit there too. Always look in the footer for supplier_niu / supplier_rccm.
            - The CLIENT / buyer is the party named after « Doit : », « Client : »,
              « À : » or « Facturé à : ». Any NIU/RCCM printed next to that party is
              the CLIENT's (client_niu / client_rccm) — NEVER put it in supplier_niu.
              Do not swap supplier and client.
            - Amounts (ht_amount, vat_amount, ttc_amount) are WHOLE FCFA integers —
              no decimals, no thousands separators.
            - vat_rate is the VAT rate as a decimal fraction, e.g. 0.1925 for 19.25%.
            - If the invoice shows a single overall TOTAL with no VAT line (common
              for small vendors), set BOTH ht_amount and ttc_amount to that total
              and omit vat_amount and vat_rate.
            - date is the invoice date as YYYY-MM-DD.
            - supplier_niu / client_niu are 14-char NIUs (P or M + 12 digits + a letter).
            - exemption: 'exonere' if the invoice is marked « Exonérée »,
              'prise_en_charge_etat' if « prise en charge État », otherwise 'none'.
            - raw_text: transcribe ALL text you can read on the page, top to bottom,
              INCLUDING the footer verbatim. Always fill this.
            - Omit any other field that does not appear on the invoice.
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
                    'supplier_niu' => ['type' => 'string', 'description' => 'The SUPPLIER (issuer) NIU — 14 chars. Often in the footer, labelled « Contribuable N° ». Never the client\'s NIU.'],
                    'client_niu' => ['type' => 'string', 'description' => 'The CLIENT/buyer NIU (14 chars), printed next to « Doit »/« Client »/« À ».'],
                    'date' => ['type' => 'string', 'description' => 'Invoice date, YYYY-MM-DD'],
                    'invoice_number' => ['type' => 'string', 'description' => 'Invoice number / reference printed on the invoice'],
                    'supplier_name' => ['type' => 'string', 'description' => 'Supplier business name (the issuer, on the letterhead)'],
                    'supplier_address' => ['type' => 'string', 'description' => 'Supplier full address (letterhead or footer)'],
                    'supplier_rccm' => ['type' => 'string', 'description' => 'Supplier RCCM number — often in the footer'],
                    'client_identity' => ['type' => 'string', 'description' => 'Client full identity/name (the « Doit »/« Client » party)'],
                    'client_address' => ['type' => 'string', 'description' => 'Client (buyer) full billing address'],
                    'client_rccm' => ['type' => 'string', 'description' => 'Client (buyer) RCCM / trade-register number, if shown'],
                    'description' => ['type' => 'string', 'description' => 'Nature/object of the operation'],
                    'ht_amount' => ['type' => 'integer', 'description' => 'Total excl. tax, whole FCFA'],
                    'vat_rate' => ['type' => 'number', 'description' => 'VAT rate as a fraction (0.1925)'],
                    'vat_amount' => ['type' => 'integer', 'description' => 'VAT amount, whole FCFA'],
                    'ttc_amount' => ['type' => 'integer', 'description' => 'Total incl. tax, whole FCFA'],
                    'exemption' => ['type' => 'string', 'enum' => ['none', 'exonere', 'prise_en_charge_etat']],
                    'raw_text' => ['type' => 'string', 'description' => 'All text read off the page, top to bottom, INCLUDING the footer, verbatim.'],
                ],
            ],
        ];
    }
}
