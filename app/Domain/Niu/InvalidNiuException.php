<?php

declare(strict_types=1);

namespace App\Domain\Niu;

use InvalidArgumentException;

class InvalidNiuException extends InvalidArgumentException
{
    public static function for(string $raw): self
    {
        return new self("NIU invalide : « {$raw} ».");
    }
}
