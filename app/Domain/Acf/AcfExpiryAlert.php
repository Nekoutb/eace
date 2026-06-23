<?php

declare(strict_types=1);

namespace App\Domain\Acf;

/**
 * The expiry-reminder cue shown on the Certificates screen, escalating as a
 * certificate nears (then passes) its expiry: a steady dot while there is ample
 * time, an orange flashing dot inside the orange window, red inside the red
 * window, and a solid red once expired.
 *
 * This is a purely visual reminder layer driven by the `niu.acf_reminder`
 * thresholds — deliberately distinct from {@see AcfStatus}, which uses the
 * settlement buffer and gates payment reuse.
 */
enum AcfExpiryAlert: string
{
    case None = 'none';        // beyond the orange window — no nudge needed
    case Orange = 'orange';    // within orange_days of expiry
    case Red = 'red';          // within red_days of expiry
    case Expired = 'expired';  // past expiry

    /** CSS modifier class for the dot rendered in the table. */
    public function dotClass(): string
    {
        return match ($this) {
            self::None => 'niu-exp-dot--ok',
            self::Orange => 'niu-exp-dot--orange',
            self::Red => 'niu-exp-dot--red',
            self::Expired => 'niu-exp-dot--expired',
        };
    }

    /** Whether the dot should pulse (only the two pre-expiry warning levels). */
    public function flashing(): bool
    {
        return $this === self::Orange || $this === self::Red;
    }

    /** BrandColors / Filament colour key. */
    public function color(): string
    {
        return match ($this) {
            self::None => 'success',
            self::Orange => 'warning',
            self::Red, self::Expired => 'danger',
        };
    }
}
