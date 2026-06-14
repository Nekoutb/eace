<?php

declare(strict_types=1);

namespace App\Services\Evidence;

use App\Models\Verification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Retention for the audit trail. Builds a compact, tamper-proof MANIFEST (the
 * facts + SHA-256 hashes of every piece of evidence) that is kept forever, packs
 * the full evidence (JSON + the scanned PDFs) into a downloadable ZIP for the
 * user to keep off-platform, and then PURGES the bulky files to free space —
 * leaving only the manifest, against which the downloaded archive can be verified.
 */
final class EvidenceArchiveService
{
    /**
     * The immutable proof kept after the bulky evidence is purged. File hashes
     * are computed from the files while they still exist.
     *
     * @return array<string, mixed>
     */
    public function manifestFor(Verification $verification): array
    {
        $snapshot = $verification->snapshot();
        $acf = $verification->acfCertificate;

        return [
            'niu' => $verification->niu,
            'raw_input' => $verification->raw_input,
            'stage' => $verification->stage->value,
            'verdict' => $verification->verdict->value,
            'approval' => $verification->approval?->value,
            'exposure_xaf' => (int) $verification->exposure_xaf,
            'verified_at' => $verification->created_at?->toIso8601String(),
            'checks' => $this->passMap($verification->checks),
            'conformity' => $this->passMap($verification->invoice_checks),
            'evidence' => array_filter([
                'snapshot_sha256' => $snapshot?->content_hash,
                'snapshot_source' => $snapshot?->source,
                'acf_reference' => $acf?->reference,
                'acf_file_sha256' => $this->fileHash($acf?->file_path),
                'invoice_file_sha256' => $this->fileHash($verification->invoice_file_path),
            ], fn (mixed $v): bool => $v !== null),
        ];
    }

    /**
     * Build a downloadable ZIP of the full evidence (JSON + PDFs + manifest).
     * Returns the absolute path to the created archive.
     */
    public function exportZip(Verification $verification): string
    {
        $payload = [
            'verification' => [
                'niu' => $verification->niu,
                'raw_input' => $verification->raw_input,
                'stage' => $verification->stage->value,
                'verdict' => $verification->verdict->value,
                'approval' => $verification->approval?->value,
                'exposure_xaf' => (int) $verification->exposure_xaf,
                'verified_at' => $verification->created_at?->toIso8601String(),
                'checks' => $verification->checks,
                'invoice_checks' => $verification->invoice_checks,
                'triggered_rules' => $verification->triggered_rules,
            ],
            'fiscalis_snapshot' => $verification->snapshot()?->payload,
            'manifest' => $verification->manifest ?? $this->manifestFor($verification),
        ];

        $dir = storage_path('app/exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.'/evidence-'.$verification->getKey().'.zip';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('evidence.json', (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->addFileToZip($zip, 'acf.pdf', $verification->acfCertificate?->file_path);
        $this->addFileToZip($zip, 'invoice.pdf', $verification->invoice_file_path);
        $zip->close();

        return $path;
    }

    /**
     * Purge the bulky evidence (the scanned PDFs) to free space, keeping the
     * tamper-proof manifest. Idempotent.
     */
    public function archive(Verification $verification): void
    {
        if ($verification->isArchived()) {
            return;
        }

        $manifest = $this->manifestFor($verification); // hashes the files before deletion

        $disk = Storage::disk('local');
        foreach ([$verification->invoice_file_path, $verification->acfCertificate?->file_path] as $relativePath) {
            if ($relativePath !== null && $disk->exists($relativePath)) {
                $disk->delete($relativePath);
            }
        }

        $verification->forceFill([
            'manifest' => $manifest,
            'invoice_data' => null, // drop the raw extracted figures; the manifest keeps the hashes
            'archived_at' => now(),
        ])->save();
    }

    /**
     * Verifications older than $months that have not been archived yet.
     *
     * @return Collection<int, Verification>
     */
    public function eligibleForArchival(int $months): Collection
    {
        return Verification::query()
            ->whereNull('archived_at')
            ->where('created_at', '<', now()->subMonths($months))
            ->get();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $rows
     * @return array<string, bool>
     */
    private function passMap(?array $rows): array
    {
        $map = [];
        foreach ($rows ?? [] as $row) {
            if (isset($row['key'])) {
                $map[(string) $row['key']] = (bool) ($row['passed'] ?? false);
            }
        }

        return $map;
    }

    private function fileHash(?string $relativePath): ?string
    {
        if ($relativePath === null) {
            return null;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($relativePath)) {
            return null;
        }

        return hash('sha256', (string) $disk->get($relativePath));
    }

    private function addFileToZip(ZipArchive $zip, string $name, ?string $relativePath): void
    {
        if ($relativePath === null) {
            return;
        }

        $disk = Storage::disk('local');
        if ($disk->exists($relativePath)) {
            $zip->addFromString($name, (string) $disk->get($relativePath));
        }
    }
}
