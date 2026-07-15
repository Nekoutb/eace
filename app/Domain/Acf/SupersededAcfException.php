<?php

declare(strict_types=1);

namespace App\Domain\Acf;

use App\Models\AcfCertificate;
use RuntimeException;

/**
 * Thrown when an uploaded ACF is refused because the certificate register
 * already holds one for the same vendor whose validity runs as long or longer
 * — only a certificate with a LATER validity date may replace the registered one.
 */
class SupersededAcfException extends RuntimeException
{
    public static function make(AcfCertificate $incumbent): self
    {
        return new self((string) __('app.ui.acf_superseded', [
            'reference' => (string) $incumbent->reference,
            'date' => $incumbent->expires_at->format('d/m/Y'),
        ]));
    }
}
