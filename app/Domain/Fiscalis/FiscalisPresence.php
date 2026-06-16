<?php

declare(strict_types=1);

namespace App\Domain\Fiscalis;

/**
 * Result of a live consultation of the DGI "Fichier National des Contribuables".
 * Presence on the national file is the registration/active confirmation — the
 * portal exposes no separate active/inactive flag, so "present" == registered.
 */
enum FiscalisPresence: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Unverified = 'unverified';

    public function label(): string
    {
        return (string) __('app.fiscalis_presence.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Unverified => 'gray',
        };
    }
}
