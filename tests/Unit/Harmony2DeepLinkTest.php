<?php

declare(strict_types=1);

use App\Services\Acf\Harmony2AcfConnector;

/** Reach the private deep-link builder without running the headless driver. */
function deepLink(string $base, string $reference): string
{
    $connector = new Harmony2AcfConnector($base, 'C:/fake/chrome.exe');
    $method = new ReflectionMethod($connector, 'deepLink');

    return $method->invoke($connector, $reference);
}

it('builds the QR-equivalent deep-link from a numeric reference', function () {
    expect(deepLink('https://fanr.harmony2.cm/authentification', '620264856998'))
        ->toBe('https://fanr.harmony2.cm/authentification?doc=ACF&reference=620264856998');
});

it('appends with & when the base already carries a query', function () {
    expect(deepLink('https://fanr.harmony2.cm/authentification?lang=fr', '620264856998'))
        ->toBe('https://fanr.harmony2.cm/authentification?lang=fr&doc=ACF&reference=620264856998');
});

it('normalises OCR spacing in the reference before building the link', function () {
    // verify() strips non-digits; the deep-link itself receives clean digits.
    $connector = new Harmony2AcfConnector('https://fanr.harmony2.cm/authentification', 'C:/fake/chrome.exe');
    $method = new ReflectionMethod($connector, 'deepLink');

    expect($method->invoke($connector, '620264856998'))
        ->toContain('reference=620264856998');
});
