<?php

declare(strict_types=1);

use App\Support\ChildProcessEnv;

it('passes the essential Windows variables to a spawned node process', function () {
    $env = ChildProcessEnv::forNode();

    if (PHP_OS_FAMILY === 'Windows') {
        // SystemRoot must be present, else node aborts during crypto init
        // (Assertion failed: ncrypto::CSPRNG) when spawned from a web request.
        expect($env)->toBeArray()
            ->and($env)->toHaveKey('SystemRoot');
    } else {
        // Nothing to fix off Windows — inherit the parent environment.
        expect($env)->toBeNull();
    }
});
