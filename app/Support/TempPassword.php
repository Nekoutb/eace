<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Generates a one-time temporary password for a freshly provisioned user. Drawn
 * with random_int (CSPRNG) from an unambiguous alphabet — no 0/O, 1/l/I — so it
 * survives being read aloud or copied by hand during the hand-over.
 */
final class TempPassword
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    public static function generate(int $length = 12): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= self::ALPHABET[random_int(0, $max)];
        }

        return $password;
    }
}
