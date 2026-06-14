<?php

declare(strict_types=1);

namespace App\Domain\Acf;

use RuntimeException;

class UnreadableAcfException extends RuntimeException
{
    public static function make(): self
    {
        return new self('ACF illisible : la référence ou la date de signature est introuvable dans le document.');
    }
}
