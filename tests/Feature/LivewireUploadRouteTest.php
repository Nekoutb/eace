<?php

declare(strict_types=1);

use App\Http\Middleware\InitializeTenancyForLivewire;

/*
 * Guard the ACF-upload bug: Livewire's temporary file-upload route ships with
 * only a throttle, so on a tenant subdomain it runs in central context and
 * bounces the user to login. It must carry the web (session) group and the
 * tenancy-aware middleware.
 */

it('makes the Livewire upload route session and tenancy aware', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => str_contains((string) $r->uri(), 'upload-file'));

    expect($route)->not->toBeNull();

    // The route carries the 'web' group (session) and the tenancy-aware
    // middleware; gatherMiddleware() lists them before the group is expanded.
    $middleware = $route->gatherMiddleware();

    expect($middleware)->toContain(InitializeTenancyForLivewire::class)
        ->and($middleware)->toContain('web');
});
