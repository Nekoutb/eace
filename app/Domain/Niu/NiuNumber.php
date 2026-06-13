<?php

declare(strict_types=1);

namespace App\Domain\Niu;

use Stringable;

/**
 * Value object for a Cameroonian Numéro d'Identifiant Unique (NIU).
 *
 * Format: one letter (P|M) + 12 digits + one control letter = 14 characters
 * (e.g. P058712345678X). See CGI 2025 and the Fiscalis taxpayer-file article.
 *
 * Immutable. Always normalised (upper-case, no separators) before validation.
 */
final class NiuNumber implements Stringable
{
    public const LENGTH = 14;

    public const PATTERN = '/^[PM][0-9]{12}[A-Z]$/';

    /**
     * Visually ambiguous characters that get mistyped into the 12 digit
     * positions. The article cites O/0, l/1 and B/8 as the main causes of the
     * ~22 % of invoice NIUs that fail validation; the rest are standard OCR
     * confusions. Conservative on purpose — over-correcting would be worse.
     *
     * @var array<string, string>
     */
    private const LETTER_TO_DIGIT = [
        'O' => '0', 'D' => '0', 'Q' => '0',
        'I' => '1', 'L' => '1',
        'Z' => '2',
        'A' => '4',
        'S' => '5',
        'G' => '6',
        'T' => '7',
        'B' => '8',
    ];

    /**
     * Reverse confusions for the trailing control letter, when a digit was
     * typed where a letter belongs. Keys are numeric strings, so PHP stores
     * them as integers — hence the int key type.
     *
     * @var array<int, string>
     */
    private const DIGIT_TO_LETTER = [
        '0' => 'O',
        '1' => 'I',
        '2' => 'Z',
        '5' => 'S',
        '6' => 'G',
        '8' => 'B',
    ];

    private function __construct(
        public readonly string $value,
    ) {}

    /**
     * Strip every non-alphanumeric character and upper-case the rest.
     * Handles stray spaces, dashes and the "espace en trop" the article warns about.
     */
    public static function normalize(string $raw): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $raw));
    }

    public static function isValid(string $candidate): bool
    {
        return preg_match(self::PATTERN, $candidate) === 1;
    }

    /**
     * Build from raw input, throwing if it cannot be normalised to a valid NIU.
     */
    public static function fromString(string $raw): self
    {
        $normalized = self::normalize($raw);

        if (! self::isValid($normalized)) {
            throw InvalidNiuException::for($raw);
        }

        return new self($normalized);
    }

    /**
     * Build from raw input, or return null when it is not a valid NIU.
     */
    public static function tryFrom(string $raw): ?self
    {
        $normalized = self::normalize($raw);

        return self::isValid($normalized) ? new self($normalized) : null;
    }

    /**
     * Suggest a corrected NIU for input that fails validation only because of
     * ambiguous characters (O↔0, l↔1, B↔8, …). Returns null when the input is
     * already valid, has the wrong length, or cannot be repaired by
     * position-aware substitution alone.
     */
    public static function suggest(string $raw): ?string
    {
        $normalized = self::normalize($raw);

        if (self::isValid($normalized) || strlen($normalized) !== self::LENGTH) {
            return null;
        }

        $repaired = self::repairByPosition($normalized);

        return self::isValid($repaired) ? $repaired : null;
    }

    /**
     * Apply the expected character class at each position: letter prefix,
     * 12 digits, letter suffix. Substitutes only known ambiguous characters.
     */
    private static function repairByPosition(string $normalized): string
    {
        $chars = str_split($normalized);

        foreach ($chars as $i => $char) {
            if ($i >= 1 && $i <= 12) {
                $chars[$i] = self::LETTER_TO_DIGIT[$char] ?? $char;
            } elseif ($i === 13) {
                $chars[$i] = self::DIGIT_TO_LETTER[$char] ?? $char;
            }
        }

        return implode('', $chars);
    }

    public function entityType(): NiuEntityType
    {
        return NiuEntityType::from($this->value[0]);
    }

    public function isPersonnePhysique(): bool
    {
        return $this->entityType() === NiuEntityType::PersonnePhysique;
    }

    public function isPersonneMorale(): bool
    {
        return $this->entityType() === NiuEntityType::PersonneMorale;
    }

    /**
     * Grouped display for the UI, e.g. "P 0587 1234 5678 X".
     * The mono font's slashed zero plus this grouping is what defuses the O/0 trap.
     */
    public function formatted(): string
    {
        $prefix = $this->value[0];
        $digits = substr($this->value, 1, 12);
        $suffix = $this->value[13];

        return $prefix.' '.implode(' ', str_split($digits, 4)).' '.$suffix;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
