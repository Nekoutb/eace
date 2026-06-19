<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * A tenant staff member's role. Three responsibilities, by design:
 * - Admin runs the organisation (manage users, edit the buyer identity and the
 *   accepted name variations) AND can do every compliance task.
 * - Recorder handles invoice RECORDING only (record an invoice: decide whether it
 *   may be entered into the books).
 * - Settler handles invoice SETTLEMENT only (settle an invoice: decide whether it
 *   may be paid).
 * The recording / settlement split lets an organisation separate the staff who
 * book invoices from the staff who release payments (segregation of duties).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Recorder = 'recorder';
    case Settler = 'settler';

    public function label(): string
    {
        return (string) __('app.role.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'success',
            self::Recorder => 'info',
            self::Settler => 'warning',
        };
    }

    /** May this role record invoices (the recording stage)? */
    public function canRecord(): bool
    {
        return $this === self::Admin || $this === self::Recorder;
    }

    /** May this role settle invoices (the settlement stage)? */
    public function canSettle(): bool
    {
        return $this === self::Admin || $this === self::Settler;
    }

    /**
     * Options for a Filament select, keyed by enum value.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Admin->value => self::Admin->label(),
            self::Recorder->value => self::Recorder->label(),
            self::Settler->value => self::Settler->label(),
        ];
    }
}
