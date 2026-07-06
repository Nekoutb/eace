<?php

declare(strict_types=1);

namespace App\Services\Acf;

use App\Domain\Acf\AcfExtract;
use App\Domain\Niu\NiuNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads a SCANNED ACF (photo, scan, or image-only PDF) with Anthropic's Claude
 * vision — the same engine that already reads scanned invoices. The document is
 * sent to the Messages API with a forced `extract_acf` tool so Claude returns
 * structured fields, mapped into the same AcfExtract the text parser produces;
 * everything downstream (validation, dedupe, persistence) is unchanged. Digital
 * PDFs never reach this class — the free local text parser handles those.
 * Graceful: any API failure yields an unusable extract, never an exception.
 */
final class ClaudeAcfOcr implements AcfOcr
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    public function __construct(
        private string $apiKey,
        private string $model,
    ) {}

    public function read(string $bytes, string $mediaType): AcfExtract
    {
        try {
            return $this->mapResult($this->extract($bytes, $mediaType));
        } catch (Throwable $e) {
            report($e);

            return $this->mapResult([]); // unusable — the caller decides what that means
        }
    }

    /**
     * Map the extract_acf tool input into the domain extract. Pure — unit-tested.
     *
     * @param  array<string, mixed>  $toolInput
     */
    public function mapResult(array $toolInput): AcfExtract
    {
        $string = static function (string $key) use ($toolInput): ?string {
            $value = $toolInput[$key] ?? null;

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        };

        $issuedAt = null;
        if ($string('issued_at') !== null) {
            try {
                $issuedAt = CarbonImmutable::createFromFormat('Y-m-d', (string) $string('issued_at'))->startOfDay();
            } catch (Throwable) {
                $issuedAt = null;
            }
        }

        $reference = $string('reference');
        if ($reference !== null && preg_match('/^\d{6,}$/', $reference) !== 1) {
            $reference = null; // an ACF reference is always a long digit string
        }

        return new AcfExtract(
            reference: $reference,
            niu: $string('niu') !== null ? NiuNumber::tryFrom((string) $string('niu')) : null,
            taxpayerName: $string('taxpayer_name'),
            issuedAt: $issuedAt,
            validityMonths: is_numeric($toolInput['validity_months'] ?? null) ? (int) $toolInput['validity_months'] : null,
            hasMoratorium: (bool) ($toolInput['has_moratorium'] ?? false),
            regime: $string('regime'),
            centre: $string('centre'),
            authenticityUrl: $string('authenticity_url'),
        );
    }

    /**
     * @return array<string, mixed> the extract_acf tool input
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
            'tool_choice' => ['type' => 'tool', 'name' => 'extract_acf'],
            'messages' => [[
                'role' => 'user',
                'content' => [
                    $documentBlock,
                    ['type' => 'text', 'text' => $this->instructions()],
                ],
            ]],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Claude ACF extraction failed: HTTP '.$response->status());
        }

        foreach ((array) $response->json('content', []) as $block) {
            if (is_array($block)
                && ($block['type'] ?? null) === 'tool_use'
                && ($block['name'] ?? null) === 'extract_acf'
                && is_array($block['input'] ?? null)
            ) {
                /** @var array<string, mixed> $input */
                $input = $block['input'];

                return $input;
            }
        }

        throw new \RuntimeException('Claude returned no structured ACF data.');
    }

    private function instructions(): string
    {
        return <<<'TXT'
            You are reading a Cameroon "Attestation de Conformité Fiscale" (ACF /
            Certificate of Tax Compliance) issued by the DGI — this copy is a scan
            or photo. Read it carefully and return the fields by calling the
            extract_acf tool. Rules:
            - reference: the ACF reference number — digits only (usually 12 digits
              starting with 620). It appears after "Réference ACF" / "ACF Reference"
              and inside the authentication URL (…?reference=NNNN).
            - niu: the taxpayer's NIU — exactly 14 characters (P or M + 12 digits +
              a letter). Read it character by character.
            - issued_at: the validity START date ("à compter du DD/MM/YYYY" /
              "from DD/MM/YYYY"), returned as YYYY-MM-DD. If absent, use the header
              issue date (e.g. "YAOUNDE : DD/MM/YYYY").
            - validity_months: the validity duration in months (usually 3).
            - has_moratorium: true only if the document mentions "sursis" or
              "moratoire".
            - regime: the tax regime as printed (e.g. REEL, SIMPLIFIE, IGS).
            - centre: the "Centre des impôts" as printed.
            - authenticity_url: the full verification URL if printed.
            - Omit any field you cannot read with confidence — never guess digits.
            TXT;
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(): array
    {
        return [
            'name' => 'extract_acf',
            'description' => 'Return the structured fields read off the scanned ACF certificate.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'reference' => ['type' => 'string', 'description' => 'ACF reference, digits only (often 12 digits starting 620)'],
                    'niu' => ['type' => 'string', 'description' => 'Taxpayer NIU, 14 chars: P/M + 12 digits + letter'],
                    'taxpayer_name' => ['type' => 'string', 'description' => 'Taxpayer / business name as printed'],
                    'issued_at' => ['type' => 'string', 'description' => 'Validity start date, YYYY-MM-DD'],
                    'validity_months' => ['type' => 'integer', 'description' => 'Validity in months (usually 3)'],
                    'has_moratorium' => ['type' => 'boolean', 'description' => 'True if "sursis" or "moratoire" appears'],
                    'regime' => ['type' => 'string', 'description' => 'Tax regime as printed'],
                    'centre' => ['type' => 'string', 'description' => 'Centre des impôts as printed'],
                    'authenticity_url' => ['type' => 'string', 'description' => 'Verification URL if printed'],
                ],
            ],
        ];
    }
}
