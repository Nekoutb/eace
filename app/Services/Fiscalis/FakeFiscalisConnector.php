<?php

declare(strict_types=1);

namespace App\Services\Fiscalis;

/**
 * No-network stand-in used in tests and whenever the headless browser is
 * disabled. Always reports "unverified" — never fabricates a presence verdict.
 */
final class FakeFiscalisConnector implements FiscalisConnector
{
    public function __construct(
        private string $url = 'https://teledeclaration-dgi.cm/modules/immatriculation/Consultation/listecontribuable.aspx',
    ) {}

    public function check(string $niu): FiscalisResult
    {
        return FiscalisResult::unverified($this->url, 'headless browser disabled');
    }
}
