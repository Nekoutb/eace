<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\ThirdParty;

/*
 * The keystone guarantee of the whole product: one tenant can NEVER read
 * another tenant's data. This test stays in CI permanently — if it ever fails,
 * the multi-tenancy boundary is broken and nothing ships.
 */

afterEach(function () {
    Tenant::all()->each->delete(); // triggers DeleteDatabase, removing tenant sqlite files
});

it('keeps third parties invisible across tenants', function () {
    $alpha = Tenant::create(['id' => 'alpha']);
    $beta = Tenant::create(['id' => 'beta']);

    tenancy()->initialize($alpha);
    ThirdParty::create(['name' => 'Fournisseur Alpha', 'type' => 'supplier']);
    expect(ThirdParty::count())->toBe(1);
    tenancy()->end();

    tenancy()->initialize($beta);
    expect(ThirdParty::count())->toBe(0); // beta cannot see alpha's supplier
    ThirdParty::create(['name' => 'Client Beta', 'type' => 'client']);
    expect(ThirdParty::count())->toBe(1);
    tenancy()->end();

    tenancy()->initialize($alpha);
    expect(ThirdParty::pluck('name')->all())->toBe(['Fournisseur Alpha']);
    tenancy()->end();
});
