<?php

declare(strict_types=1);

namespace App\Services\Fiscalis;

/**
 * Confirms a NIU's presence on the DGI "Fichier National des Contribuables"
 * (the live registration/active check behind the four-check verdict).
 *
 * Implementations must never throw: an unreachable or undriveable portal
 * returns an "unverified" result so the verification degrades gracefully.
 */
interface FiscalisConnector
{
    public function check(string $niu): FiscalisResult;
}
