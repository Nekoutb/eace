<?php

declare(strict_types=1);

namespace App\Domain\Acf;

/**
 * Lifecycle state of an Attestation de Conformité Fiscale, recomputed by the
 * monitor and on each settlement read. Drives the validity badge and the
 * settlement reuse/block decision (CGI art. L 94 bis: 3-month validity, 1 month
 * under a payment moratorium).
 */
enum AcfStatus: string
{
    case Valid = 'valid';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case NotAuthentic = 'not_authentic';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valide',
            self::ExpiringSoon => 'Bientôt expirée',
            self::Expired => 'Expirée',
            self::NotAuthentic => 'Non authentique',
        };
    }

    /** BrandColors / Filament colour key. */
    public function color(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::ExpiringSoon => 'warning',
            self::Expired => 'danger',
            self::NotAuthentic => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Valid => 'heroicon-o-check-badge',
            self::ExpiringSoon => 'heroicon-o-clock',
            self::Expired => 'heroicon-o-x-circle',
            self::NotAuthentic => 'heroicon-o-shield-exclamation',
        };
    }

    /** Whether a settlement may proceed on this certificate (must be cleanly valid). */
    public function allowsSettlement(): bool
    {
        return $this === self::Valid;
    }
}
