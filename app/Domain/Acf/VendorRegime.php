<?php

declare(strict_types=1);

namespace App\Domain\Acf;

/**
 * The vendor's régime d'imposition as printed on their ACF (or, as a fallback,
 * in the DGI taxpayer register): Régime du Réel, Régime Simplifié, or Impôt
 * Général Synthétique. Drives the VAT-invoicing coherence check — only Réel
 * vendors may invoice VAT. Unknown = the régime could not be established.
 */
enum VendorRegime: string
{
    case Reel = 'reel';
    case Simplifie = 'simplifie';
    case Igs = 'igs';
    case Unknown = 'unknown';

    /**
     * Normalise the free-text régime label found on documents: accents and case
     * vary ("RÉEL", "Régime du réel", "IGS", "Impôt Général Synthétique",
     * "SIMPLIFIÉ", "R.SIMPLIFIE"…).
     */
    public static function fromLabel(?string $label): self
    {
        $normalized = mb_strtoupper(trim((string) $label));
        // Strip the accents the portals/documents use inconsistently.
        $normalized = strtr($normalized, ['É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ô' => 'O', 'Î' => 'I']);

        if ($normalized === '') {
            return self::Unknown;
        }

        if (str_contains($normalized, 'IGS') || str_contains($normalized, 'GENERAL SYNTH') || str_contains($normalized, 'SYNTHETIQUE')) {
            return self::Igs;
        }

        if (str_contains($normalized, 'SIMPLIF')) {
            return self::Simplifie;
        }

        if (str_contains($normalized, 'REEL')) {
            return self::Reel;
        }

        return self::Unknown;
    }

    public function label(): string
    {
        return (string) __('app.regime.'.$this->value);
    }

    /** Only Réel vendors may invoice VAT (CGI — régimes d'imposition). */
    public function mayInvoiceVat(): bool
    {
        return $this === self::Reel;
    }
}
