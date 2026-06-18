<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Environment to hand a spawned Node process.
 *
 * On Windows, a child spawned from `php artisan serve` (or php-fpm) inherits a
 * stripped environment that can be missing SystemRoot. Node then aborts during
 * crypto initialisation with "Assertion failed: ncrypto::CSPRNG(nullptr, 0)"
 * (exit 134) — so the headless-browser drivers produced no output and every
 * snapshot came back "unverified". Passing the essential Windows variables
 * through fixes it. On non-Windows hosts there is nothing to fix: return null
 * so the child simply inherits the parent environment.
 */
final class ChildProcessEnv
{
    /**
     * @return array<string, string>|null
     */
    public static function forNode(): ?array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $keep = [
            'SystemRoot', 'windir', 'SystemDrive', 'PATH', 'Path', 'PATHEXT',
            'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA',
            'HOMEDRIVE', 'HOMEPATH', 'COMSPEC', 'NUMBER_OF_PROCESSORS', 'PROCESSOR_ARCHITECTURE',
        ];

        $env = [];
        foreach ($keep as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $env[$key] = $value;
            }
        }

        return $env;
    }
}
