<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

/*
 * Guard against the "no such table: sessions" class of bug: with database-backed
 * session/cache/queue drivers, tenancy switches the default connection to the
 * tenant DB, so each tenant must carry Laravel's framework tables. The test
 * suite runs on array/sync drivers, so only this structural check catches a
 * missing framework migration.
 */

afterEach(function () {
    Tenant::all()->each->delete();
});

it('provisions the framework infrastructure tables in every tenant database', function () {
    $tenant = Tenant::create(['id' => 'infra']);
    tenancy()->initialize($tenant);

    expect(Schema::hasTable('sessions'))->toBeTrue()
        ->and(Schema::hasTable('cache'))->toBeTrue()
        ->and(Schema::hasTable('jobs'))->toBeTrue()
        ->and(Schema::hasTable('users'))->toBeTrue();

    tenancy()->end();
});
