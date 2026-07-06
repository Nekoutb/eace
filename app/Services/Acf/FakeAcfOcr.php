<?php

declare(strict_types=1);

namespace App\Services\Acf;

use App\Domain\Acf\AcfExtract;

/**
 * No-network OCR used when no Anthropic key is configured (dev, tests): always
 * returns an unusable extract, so a scanned document simply reads as
 * "unreadable" — exactly the behaviour before OCR existed.
 */
final class FakeAcfOcr implements AcfOcr
{
    public function read(string $bytes, string $mediaType): AcfExtract
    {
        return new AcfExtract(
            reference: null,
            niu: null,
            taxpayerName: null,
            issuedAt: null,
            validityMonths: null,
            hasMoratorium: false,
            regime: null,
            centre: null,
            authenticityUrl: null,
        );
    }
}
