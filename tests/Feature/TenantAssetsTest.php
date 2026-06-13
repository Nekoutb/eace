<?php

declare(strict_types=1);

use App\Models\Tenant;

/*
 * Guard the "bland page" bug: with asset_helper_tenancy enabled, stancl rewrites
 * asset() URLs through /tenancy/assets, which 404s Filament's public CSS/JS on
 * tenant subdomains and strips all styling. Global assets must resolve from the
 * public path inside a tenant just like on the central domain.
 */

afterEach(function () {
    Tenant::all()->each->delete();
});

it('resolves global assets from the public path inside a tenant', function () {
    $tenant = Tenant::create(['id' => 'assets']);
    tenancy()->initialize($tenant);

    expect(asset('css/filament/filament/app.css'))
        ->toContain('/css/filament/filament/app.css')
        ->not->toContain('/tenancy/assets/');

    tenancy()->end();
});
