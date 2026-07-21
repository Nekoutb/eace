<?php

declare(strict_types=1);

namespace App\Domain\Acf;

/**
 * The vendor's régime d'imposition as printed on their ACF (or, as a fallback,
 * in the DGI taxpayer register). Two families matter for VAT: the Régime du
 * Réel, whose vendors may invoice VAT, and every other positively-identified
 * régime (Simplifié, IGS, and the non-business categories), whose vendors may
 * NOT. Unknown = the régime could not be established at all — treated
 * conservatively, never as licence to skip a control.
 */
enum VendorRegime: string
{
    case Reel = 'reel';
    case Simplifie = 'simplifie';
    case Igs = 'igs';
    case NonLucratif = 'non_lucratif';
    case NonProfessionnel = 'non_professionnel';
    case HorsRegime = 'hors_regime';
    case Unknown = 'unknown';

    /**
     * Normalise the free-text régime label found on documents and the DGI
     * register: accents and case vary ("RÉEL", "Régime du réel", "IGS", "Impôt
     * Général Synthétique", "SIMPLIFIÉ", "R.SIMPLIFIE", "ORGANISME À BUT NON
     * LUCRATIF", "NON PROFESSIONNEL", "HORS REGIME D'IMPOSITION"…).
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

        // Non-business categories carried in the DGI register — positively
        // non-Réel (never entitled to invoice VAT), distinct from "unknown".
        if (str_contains($normalized, 'NON LUCRATIF') || str_contains($normalized, 'BUT NON LUCRATIF')) {
            return self::NonLucratif;
        }

        if (str_contains($normalized, 'NON PROFESSIONNEL')) {
            return self::NonProfessionnel;
        }

        if (str_contains($normalized, 'HORS REGIME')) {
            return self::HorsRegime;
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

    /**
     * Whether VAT controls apply to this vendor:
     *  - true  → Régime du Réel: VAT is legitimate; run the figure checks.
     *  - false → positively non-Réel (Simplifié, IGS, non-business): VAT must
     *            not appear; its presence is an error, not an art. 150 defect.
     *  - null  → régime undetermined: judged conservatively (checks stay on),
     *            never silently disabled.
     */
    public function vatApplicable(): ?bool
    {
        return match ($this) {
            self::Reel => true,
            self::Simplifie, self::Igs, self::NonLucratif, self::NonProfessionnel, self::HorsRegime => false,
            self::Unknown => null,
        };
    }
}
