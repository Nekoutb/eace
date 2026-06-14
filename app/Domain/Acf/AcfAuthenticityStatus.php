<?php

declare(strict_types=1);

namespace App\Domain\Acf;

/**
 * Result of the Harmony2 document-authenticity check (the ACF QR → published
 * list, CGI art. L 94 bis al. 3). Unknown until check 1 runs.
 */
enum AcfAuthenticityStatus: string
{
    case Authentic = 'authentic';
    case NotAuthentic = 'not_authentic';
    case Unverified = 'unverified';

    public function label(): string
    {
        return (string) __('app.acf_auth.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Authentic => 'success',
            self::NotAuthentic => 'danger',
            self::Unverified => 'gray',
        };
    }
}
