<?php

declare(strict_types=1);

namespace App\Domain\Risk;

/**
 * How serious a triggered rule is. Drives sorting and the badge colour on a
 * finding; the overall Verdict is derived separately by the engine.
 */
enum RuleSeverity: int
{
    case Info = 1;
    case Warning = 2;
    case Blocking = 3;

    public function label(): string
    {
        return (string) __('app.severity.'.match ($this) {
            self::Info => 'info',
            self::Warning => 'warning',
            self::Blocking => 'blocking',
        });
    }

    public function color(): string
    {
        return match ($this) {
            self::Info => 'gray',
            self::Warning => 'warning',
            self::Blocking => 'danger',
        };
    }
}
