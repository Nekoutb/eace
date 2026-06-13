<?php

declare(strict_types=1);

namespace App\Domain\Risk;

use Illuminate\Support\Str;

/**
 * Fuzzy comparison of a name on an invoice against the name in the Fiscalis
 * file. Tolerant of accents, punctuation, legal-form noise and spacing, but
 * flags genuine discordance (the L 102 erroneous-invoice trap).
 */
final class NameMatcher
{
    public static function matches(string $a, string $b, float $threshold = 0.85): bool
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);

        if ($na === '' || $nb === '') {
            return false;
        }

        if ($na === $nb || str_contains($na, $nb) || str_contains($nb, $na)) {
            return true;
        }

        similar_text($na, $nb, $percent);

        return $percent >= $threshold * 100;
    }

    public static function normalize(string $value): string
    {
        $value = Str::ascii($value);
        $value = strtoupper($value);
        $value = (string) preg_replace('/[^A-Z0-9 ]/', ' ', $value);

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
