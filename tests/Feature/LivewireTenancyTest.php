<?php

declare(strict_types=1);

use App\Http\Middleware\InitializeTenancyForLivewire;
use App\Models\Tenant;
use Illuminate\Http\Request;

/*
 * Livewire's shared /livewire/update route is what the app panel's login form
 * POSTs to. Without tenancy initialisation it runs in central context and the
 * login session never lands in the tenant — so the user bounces back to the
 * login page. This proves the middleware initialises the tenant on a tenant
 * domain and stays out of the way on a central domain (admin panel).
 */

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('initialises the tenant for Livewire requests on a tenant domain', function () {
    $tenant = Tenant::create(['id' => 'live']);
    $tenant->domains()->create(['domain' => 'live.localhost']);

    $seen = null;
    app(InitializeTenancyForLivewire::class)->handle(
        Request::create('http://live.localhost/livewire/update', 'POST'),
        function () use (&$seen) {
            $seen = tenant('id');

            return response('ok');
        },
    );

    expect($seen)->toBe('live');
});

it('passes through central-domain Livewire requests without tenancy', function () {
    $seen = 'unset';
    $response = app(InitializeTenancyForLivewire::class)->handle(
        Request::create('http://localhost/livewire/update', 'POST'),
        function () use (&$seen) {
            $seen = tenant('id');

            return response('central-ok');
        },
    );

    expect($seen)->toBeNull()
        ->and($response->getContent())->toBe('central-ok');
});

it('keeps central admin-panel Livewire requests central even on a tenant host', function () {
    // Single-workspace: the tenant host also serves the central /admin console.
    // A Livewire action from /admin must NOT initialise the tenant, or the
    // central admin can never authenticate.
    $tenant = Tenant::create(['id' => 'sw']);
    $tenant->domains()->create(['domain' => 'sw.localhost']);

    $seen = 'unset';
    app(InitializeTenancyForLivewire::class)->handle(
        Request::create('http://sw.localhost/livewire/update', 'POST', [
            'components' => [['snapshot' => json_encode(['memo' => ['path' => 'admin/login']])]],
        ]),
        function () use (&$seen) {
            $seen = tenant('id');

            return response('ok');
        },
    );

    expect($seen)->toBeNull();
});

it('still initialises the tenant for app-panel Livewire requests on a tenant host', function () {
    $tenant = Tenant::create(['id' => 'sw2']);
    $tenant->domains()->create(['domain' => 'sw2.localhost']);

    $seen = null;
    app(InitializeTenancyForLivewire::class)->handle(
        Request::create('http://sw2.localhost/livewire/update', 'POST', [
            'components' => [['snapshot' => json_encode(['memo' => ['path' => 'app/login']])]],
        ]),
        function () use (&$seen) {
            $seen = tenant('id');

            return response('ok');
        },
    );

    expect($seen)->toBe('sw2');
});

it('uses the Referer to keep admin-panel requests central when no snapshot is present', function () {
    $tenant = Tenant::create(['id' => 'sw3']);
    $tenant->domains()->create(['domain' => 'sw3.localhost']);

    $request = Request::create('http://sw3.localhost/livewire/update', 'POST');
    $request->headers->set('referer', 'http://sw3.localhost/admin/support-queries');

    $seen = 'unset';
    app(InitializeTenancyForLivewire::class)->handle($request, function () use (&$seen) {
        $seen = tenant('id');

        return response('ok');
    });

    expect($seen)->toBeNull();
});
