<?php

declare(strict_types=1);

namespace App\Services\Acf;

/**
 * No-network stand-in used in tests and whenever the headless browser is
 * disabled. Always reports "unverified" — it never fabricates an authentic
 * verdict, so evidence stays honest.
 */
final class FakeAcfAuthenticityConnector implements AcfAuthenticityConnector
{
    public function __construct(private string $url = 'https://fanr.harmony2.cm/authentification') {}

    public function verify(string $reference): AcfAuthenticityResult
    {
        return AcfAuthenticityResult::unverified($this->url, 'headless browser disabled');
    }
}
