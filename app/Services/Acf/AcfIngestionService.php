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
 *
 * Two reading paths: a digital PDF is read by the free local text parser; a
 * SCANNED document (photo, scan, image-only PDF) has no text layer, so it falls
 * back to AI vision OCR (AcfOcr) — same fields, same validation, same record.
 */
final class AcfIngestionService
{
    public function __construct(
        private AcfReader $reader,
        private AcfOcr $ocr,
    ) {}

    /**
     * Read an ACF document (PDF, or a photo/scan image) stored on a (tenant)
     * disk and record it.
     */
    public function ingestFromUpload(string $diskPath, string $disk = 'local', ?int $uploadedBy = null): AcfCertificate
    {
        $context = [
            'file_path' => $diskPath,
            'created_by' => $uploadedBy,
        ];

        // Digital PDFs first: extract the text layer locally (fast, free). A
        // scanned/corrupt PDF may throw or yield no text — that's the OCR's case.
        if ($this->mediaType($diskPath) === 'application/pdf') {
            try {
                $absolutePath = Storage::disk($disk)->path($diskPath);
                $text = (new Parser)->parseFile($absolutePath)->getText();
            } catch (\Throwable) {
                $text = '';
            }

            $extract = $this->reader->fromText($text);
            if ($extract->isUsable()) {
                return $this->persist($extract, $context);
            }
        }

        // Scanned document (or an image upload): read it with vision OCR.
        $bytes = (string) (Storage::disk($disk)->get($diskPath) ?? '');
        $extract = $bytes !== '' ? $this->ocr->read($bytes, $this->mediaType($diskPath)) : null;

        if ($extract === null || ! $extract->isUsable()) {
            throw UnreadableAcfException::make();
        }

        return $this->persist($extract, $context);
    }

    private function mediaType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'application/pdf',
        };
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

        // One live certificate per vendor: a DIFFERENT certificate arriving for a
        // vendor that already has one ALWAYS replaces it — the user decides which
        // ACF is current, whatever the relative validity dates (a still-valid ACF
        // can be replaced deliberately). The registered row is overwritten in
        // place (the id survives, so past verifications keep their evidence link).
        // Re-uploading the same reference just refreshes it.
        if (! $certificate->exists && $extract->niu !== null) {
            $incumbent = AcfCertificate::query()
                ->where('niu', $extract->niu->value)
                ->orderByDesc('expires_at')
                ->first();

            if ($incumbent !== null) {
                $certificate = $incumbent;
                $certificate->reference = $extract->reference;
                // A different physical document: prior authentication, its
                // snapshot, and any expiry reminders no longer apply.
                $certificate->authenticity_status = AcfAuthenticityStatus::Unverified;
                $certificate->authenticity_snapshot_path = null;
                $certificate->authenticity_note = null;
                $certificate->last_verified_at = null;
                $certificate->reminded_at = null;
                $certificate->reminder_count = 0;
            }
        }

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
