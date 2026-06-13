<?php

namespace App\Support;

use Filament\Support\Colors\Color;

/**
 * NIU Shield design tokens — see design-system.md at the repository root.
 * Verdict semantics: success = conforme, warning = réserves,
 * danger = radié, info = accent vérification (teal).
 */
class BrandColors
{
    /**
     * @return array<string, mixed>
     */
    public static function palette(): array
    {
        return [
            'primary' => Color::hex('#0B4A8B'),
            'success' => Color::hex('#15803D'),
            'warning' => Color::hex('#B45309'),
            'danger' => Color::hex('#B91C1C'),
            'info' => Color::hex('#0F766E'),
            'gray' => Color::Slate,
        ];
    }
}
