<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * A tenant staff member's role. Coarse by design — two responsibilities:
 * Admin runs the organisation (manage users, edit the buyer identity) and does
 * everything an Operator can; Operator does the day-to-day compliance work
 * (verify NIUs, consult audit logs and attestations) but cannot manage users
 * or change settings.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';

    public function label(): string
    {
        return (string) __('app.role.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'success',
            self::Operator => 'gray',
        };
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
            self::Operator->value => self::Operator->label(),
        ];
    }
}
