<?php

declare(strict_types=1);

namespace App\Domain\Risk;

use App\Domain\Niu\NiuNumber;

/**
 * Port: resolves a NIU to what the Fiscalis file holds, or null when the NIU is
 * absent from the file. The fake implementation backs development and tests;
 * the real Fiscalis connector implements this same interface later, so nothing
 * downstream changes when it lands.
 */
interface RegistryResolver
{
    public function resolve(NiuNumber $niu): ?RegistryRecord;
}
