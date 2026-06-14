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
