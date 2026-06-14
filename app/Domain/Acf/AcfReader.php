<?php

declare(strict_types=1);

namespace App\Domain\Acf;

use App\Domain\Niu\NiuNumber;
use Carbon\CarbonImmutable;

/**
 * Reads the fields of an ACF from its extracted text. Anchors are ASCII-only on
 * purpose (the document misspells "Réference" and mixes tabs/newlines, and some
 * PDF extractors mangle accents) — see the grounded design notes. Pure: give it
 * text, get an AcfExtract; the smalot PDF parsing happens in the service.
 */
final class AcfReader
{
    public function fromText(string $text): AcfExtract
    {
        return new AcfExtract(
            reference: $this->reference($text),
            niu: $this->niu($text),
            taxpayerName: $this->taxpayerName($text),
            issuedAt: $this->issuedAt($text),
            validityMonths: $this->validityMonths($text),
            hasMoratorium: $this->hasMoratorium($text),
            regime: $this->regime($text),
            centre: $this->centre($text),
            authenticityUrl: $this->authenticityUrl($text),
        );
    }

    private function reference(string $text): ?string
    {
        // French order: "Réference ACF : NNNN" (the label is misspelled in the source).
        if (preg_match('/R[\x{00e9}e]ference\s*ACF\s*[:：]\s*(\d{6,})/iu', $text, $m) === 1) {
            return $m[1];
        }

        // English order: "ACF Reference: NNNN".
        if (preg_match('/ACF\s*Reference\s*[:：]\s*(\d{6,})/iu', $text, $m) === 1) {
            return $m[1];
        }

        // Fallback: the reference is repeated verbatim in the authenticity URL.
        return preg_match('/[?&]reference=(\d{6,})/iu', $text, $m) === 1 ? $m[1] : null;
    }

    private function niu(string $text): ?NiuNumber
    {
        return preg_match('/\b([PM]\d{12}[A-Z])\b/u', $text, $m) === 1
            ? NiuNumber::tryFrom($m[1])
            : null;
    }

    private function taxpayerName(string $text): ?string
    {
        if (preg_match('/(?:Le\s+)?contribuable\s*[:：]\s*(.+?)\s+de\s+NIU/iu', $text, $m) === 1) {
            return trim($m[1]);
        }

        return preg_match('/taxpayer\s*[:：]\s*(.+?)\s+with\s+TIN/iu', $text, $m) === 1
            ? trim($m[1])
            : null;
    }

    private function issuedAt(string $text): ?CarbonImmutable
    {
        // Preferred: the validity start ("à compter du DD/MM/YYYY"); the "à" may drop.
        if (preg_match('/(?:(?:\x{00e0}\s*)?compter\s*du|from)\s*(\d{2}\/\d{2}\/\d{4})/iu', $text, $m) === 1) {
            return $this->parseDate($m[1]);
        }

        // Fallback: the header issue date ("YAOUNDE : DD/MM/YYYY").
        return preg_match('/YAOUNDE\s*[:：]\s*(\d{2}\/\d{2}\/\d{4})/iu', $text, $m) === 1
            ? $this->parseDate($m[1])
            : null;
    }

    private function validityMonths(string $text): ?int
    {
        return preg_match('/(\d+)\s*(?:mois|month)/iu', $text, $m) === 1
            ? (int) $m[1]
            : null;
    }

    private function hasMoratorium(string $text): bool
    {
        return preg_match('/sursis|moratoire/iu', $text) === 1;
    }

    private function regime(string $text): ?string
    {
        return preg_match('/(?:Tax\s+Regime|Regime)\s*[:：]\s*(\S+)/iu', $text, $m) === 1
            ? trim($m[1])
            : null;
    }

    private function centre(string $text): ?string
    {
        return preg_match('/Centre\s+d.?imp[oô]ts\s*[:：]\s*([^\t\r\n]+)/iu', $text, $m) === 1
            ? trim($m[1])
            : null;
    }

    private function authenticityUrl(string $text): ?string
    {
        return preg_match('/(https?:\/\/\S*authentification\S*reference=\d+)/iu', $text, $m) === 1
            ? rtrim($m[1], '.')
            : null;
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('d/m/Y', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
