<?php

declare(strict_types=1);

namespace App\Services\Acf;

use App\Domain\Acf\AcfExtract;

/**
 * Reads the fields of an ACF from the document IMAGE (a scan or photo, or a PDF
 * with no text layer) — the fallback when the fast local text parser finds
 * nothing. Implementations must return an extract, unusable when nothing could
 * be read; they must not throw for an unreadable document.
 */
interface AcfOcr
{
    public function read(string $bytes, string $mediaType): AcfExtract;
}
