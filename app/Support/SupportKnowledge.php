<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reads the bilingual support knowledge base (config/support_kb.php) and localises
 * it for its two consumers: the AI bot (which builds its prompt from the English
 * text) and the in-app FAQ page (which renders the current locale, grouped by
 * category with a stable order).
 */
final class SupportKnowledge
{
    /**
     * The flat FAQ list, localised to one language.
     *
     * @return list<array{category: string, q: string, a: string}>
     */
    public static function faq(?string $locale = null): array
    {
        $locale = self::lang($locale);
        $out = [];

        foreach ((array) config('support_kb.faq', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $out[] = [
                'category' => self::pick($entry['category'] ?? '', $locale),
                'q' => self::pick($entry['q'] ?? '', $locale),
                'a' => self::pick($entry['a'] ?? '', $locale),
            ];
        }

        return $out;
    }

    /**
     * The FAQ grouped by category (insertion order preserved), localised.
     *
     * @return array<string, list<array{q: string, a: string}>>
     */
    public static function grouped(?string $locale = null): array
    {
        $grouped = [];

        foreach (self::faq($locale) as $entry) {
            $grouped[$entry['category']][] = ['q' => $entry['q'], 'a' => $entry['a']];
        }

        return $grouped;
    }

    /** The topics that always require a human (bot-internal, English). */
    /** @return list<string> */
    public static function escalationTopics(): array
    {
        return array_values(array_map(
            static fn ($t): string => trim((string) $t),
            (array) config('support_kb.escalate', []),
        ));
    }

    /** Pick one language out of an ['en' => …, 'fr' => …] value (or a plain string). */
    private static function pick(mixed $value, string $locale): string
    {
        if (is_array($value)) {
            $resolved = $value[$locale] ?? $value['en'] ?? reset($value);

            return trim((string) (is_string($resolved) ? $resolved : ''));
        }

        return trim((string) $value);
    }

    private static function lang(?string $locale): string
    {
        $locale ??= app()->getLocale();

        return str_starts_with($locale, 'fr') ? 'fr' : 'en';
    }
}
