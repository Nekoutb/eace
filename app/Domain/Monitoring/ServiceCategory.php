<?php

declare(strict_types=1);

namespace App\Domain\Monitoring;

/**
 * The kind of subscription / infrastructure service being monitored.
 */
enum ServiceCategory: string
{
    case Hosting = 'hosting';
    case Domain = 'domain';
    case Storage = 'storage';
    case Email = 'email';
    case Api = 'api';
    case Ssl = 'ssl';
    case Other = 'other';

    public function label(): string
    {
        return (string) __('monitoring.cat.'.$this->value);
    }

    /** Filament badge colour. */
    public function color(): string
    {
        return match ($this) {
            self::Hosting => 'danger',
            self::Domain => 'info',
            self::Storage => 'warning',
            self::Email => 'success',
            self::Api => 'primary',
            self::Ssl => 'gray',
            self::Other => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Hosting => 'heroicon-o-server-stack',
            self::Domain => 'heroicon-o-globe-alt',
            self::Storage => 'heroicon-o-circle-stack',
            self::Email => 'heroicon-o-envelope',
            self::Api => 'heroicon-o-cpu-chip',
            self::Ssl => 'heroicon-o-lock-closed',
            self::Other => 'heroicon-o-squares-2x2',
        };
    }

    /**
     * @return array<string, string> value => label, for a Select.
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
