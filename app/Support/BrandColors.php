<?php

namespace App\Support;

use Filament\Support\Colors\Color;

/**
 * NIU Shield design tokens — see design-system.md at the repository root.
 * The brand is built on a light-green "compliance" palette (green = conforme):
 * primary = fresh green, success = a deeper green for verdicts. Verdict
 * semantics: success = conforme, warning = réserves, danger = radié,
 * info = accent vérification (teal).
 */
class BrandColors
{
    /**
     * @return array<string, mixed>
     */
    public static function palette(): array
    {
        return [
            'primary' => Color::hex('#16A34A'), // light-green brand accent (buttons, active nav, focus)
            'success' => Color::hex('#15803D'),
            'warning' => Color::hex('#B45309'),
            'danger' => Color::hex('#B91C1C'),
            'info' => Color::hex('#0F766E'),
            'suspended' => Color::hex('#C2410C'), // verdict "Suspendu" — distinct from réserves (amber) and radié (red)
            'gray' => Color::Slate,
        ];
    }
}
