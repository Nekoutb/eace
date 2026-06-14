<?php

declare(strict_types=1);

namespace App\Domain\Niu;

/**
 * The graded outcome of a NIU verification. Never binary — the Fiscalis file
 * carries duplicates and homonyms, so a verification lands the taxpayer in one
 * of six buckets (see design-system.md §2 and the article's status table).
 *
 * Colours map to the keys registered in App\Support\BrandColors so badges
 * stay consistent across both Filament panels.
 */
enum Verdict: string
{
    case Conforme = 'conforme';
    case ConformeAvecReserves = 'conforme_avec_reserves';
    case Suspendu = 'suspendu';
    case Radie = 'radie';
    case Introuvable = 'introuvable';
    case FormatInvalide = 'format_invalide';

    public function label(): string
    {
        return (string) __('app.verdict.'.$this->value);
    }

    /** Filament/BrandColors colour key. */
    public function color(): string
    {
        return match ($this) {
            self::Conforme => 'success',
            self::ConformeAvecReserves => 'warning',
            self::Suspendu => 'suspended',
            self::Radie => 'danger',
            self::Introuvable, self::FormatInvalide => 'gray',
        };
    }

    /** Heroicon name (outline) — never communicate a verdict by colour alone. */
    public function icon(): string
    {
        return match ($this) {
            self::Conforme => 'heroicon-o-check-badge',
            self::ConformeAvecReserves => 'heroicon-o-exclamation-triangle',
            self::Suspendu => 'heroicon-o-pause-circle',
            self::Radie => 'heroicon-o-no-symbol',
            self::Introuvable => 'heroicon-o-question-mark-circle',
            self::FormatInvalide => 'heroicon-o-pencil-square',
        };
    }

    /**
     * A NIU whose verification blocks payment: an invoice from it risks a
     * non-deductible VAT/charge (CGI art. 143, L 101) or a fraudulent-NIU
     * fine (L 100). Réserves does not block but demands review first.
     */
    public function blocksPayment(): bool
    {
        return match ($this) {
            self::Conforme, self::ConformeAvecReserves => false,
            self::Suspendu, self::Radie, self::Introuvable, self::FormatInvalide => true,
        };
    }

    /** Anything short of a clean Conforme deserves a human's attention. */
    public function requiresAttention(): bool
    {
        return $this !== self::Conforme;
    }
}
