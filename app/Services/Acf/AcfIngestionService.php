<?php

declare(strict_types=1);

namespace App\Services\Acf;

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfExtract;
use App\Domain\Acf\AcfReader;
use App\Domain\Acf\AcfValidity;
use App\Domain\Acf\UnreadableAcfException;
use App\Models\AcfCertificate;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

/**
 * Ingests an ACF (tenant context): parse the PDF text, read its fields, compute
 * validity, and upsert the monitored certificate (deduped on reference + NIU).
 */
final class AcfIngestionService
{
    public function __construct(private AcfReader $reader) {}

    /**
     * Read an ACF PDF stored on a (tenant) disk and record it.
     */
    public function ingestFromUpload(string $diskPath, string $disk = 'local', ?int $uploadedBy = null): AcfCertificate
    {
        $absolutePath = Storage::disk($disk)->path($diskPath);
        $text = (new Parser)->parseFile($absolutePath)->getText();

        return $this->ingestFromText($text, [
            'file_path' => $diskPath,
            'created_by' => $uploadedBy,
        ]);
    }

    /**
     * Record an ACF from already-extracted text (kept separate so it is testable
     * without a PDF).
     *
     * @param  array{file_path?: ?string, created_by?: ?int}  $context
     */
    public function ingestFromText(string $text, array $context = []): AcfCertificate
    {
        $extract = $this->reader->fromText($text);

        if (! $extract->isUsable()) {
            throw UnreadableAcfException::make();
        }

        return $this->persist($extract, $context);
    }

    /**
     * @param  array{file_path?: ?string, created_by?: ?int}  $context
     */
    private function persist(AcfExtract $extract, array $context): AcfCertificate
    {
        $validityMonths = AcfValidity::validityMonths($extract->validityMonths, $extract->hasMoratorium);

        $certificate = AcfCertificate::query()->firstOrNew([
            'reference' => $extract->reference,
            'niu' => $extract->niu?->value,
        ]);

        $certificate->fill([
            'taxpayer_name' => $extract->taxpayerName,
            'issued_at' => $extract->issuedAt,
            'validity_months' => $validityMonths,
            'has_moratorium' => $extract->hasMoratorium,
            'regime' => $extract->regime,
            'centre' => $extract->centre,
            'authenticity_url' => $extract->authenticityUrl,
            'authenticity_status' => $certificate->authenticity_status ?? AcfAuthenticityStatus::Unverified,
            'raw_payload' => $extract->toArray(),
        ]);

        if (($context['file_path'] ?? null) !== null) {
            $certificate->file_path = $context['file_path'];
        }

        if (! $certificate->exists && ($context['created_by'] ?? null) !== null) {
            $certificate->created_by = $context['created_by'];
        }

        $certificate->recomputeValidity()->save();

        return $certificate;
    }
}
