<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\ClientProvisioningException;
use App\Services\Tenant\ClientProvisioningService;
use App\Services\Tenant\ProvisionedClient;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Database\Models\Domain;

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    // Deleting a tenant drops its database (TenantDeleted → DeleteDatabase).
    Tenant::all()->each->delete();
    // File-based tenant DBs can outlive a rolled-back RefreshDatabase transaction;
    // sweep the ones these tests use so a leftover file never bricks a re-run.
    foreach (['acme', 'beta', 'acme-corp', 'orphan'] as $id) {
        $file = database_path('tenant'.$id.'.sqlite');
        if (is_file($file)) {
            @unlink($file);
        }
    }
});

function provision(string $subdomain = 'acme'): ProvisionedClient
{
    return app(ClientProvisioningService::class)->provision(
        name: 'Acme Trading SARL',
        subdomain: $subdomain,
        adminName: 'Jane Admin',
        adminEmail: "jane@{$subdomain}.test",
    );
}

it('provisions an isolated client with an admin carrying a working temporary password', function () {
    $client = provision('acme');

    expect($client->tenant->id)->toBe('acme')
        ->and($client->tenant->name)->toBe('Acme Trading SARL')
        ->and($client->domain)->toBe('acme.localhost')
        ->and(strlen($client->temporaryPassword))->toBe(12);

    $client->tenant->run(function () use ($client) {
        $admin = User::query()->sole();

        expect($admin->email)->toBe('jane@acme.test')
            ->and($admin->role)->toBe(UserRole::Admin)
            ->and($admin->isAdmin())->toBeTrue()
            ->and($admin->must_change_password)->toBeTrue()
            ->and(Hash::check($client->temporaryPassword, $admin->password))->toBeTrue();
    });
});

it('keeps each client database isolated from the others', function () {
    $acme = provision('acme');
    $beta = provision('beta');

    $acme->tenant->run(fn () => expect(User::query()->pluck('email')->all())->toBe(['jane@acme.test']));
    $beta->tenant->run(fn () => expect(User::query()->pluck('email')->all())->toBe(['jane@beta.test']));
});

it('rejects a subdomain that is already taken', function () {
    provision('acme');

    expect(fn () => provision('acme'))->toThrow(ClientProvisioningException::class);
});

it('rejects a reserved subdomain and leaves no tenant behind', function () {
    expect(fn () => app(ClientProvisioningService::class)->provision('X', 'admin', 'A', 'a@x.test'))
        ->toThrow(ClientProvisioningException::class);

    expect(Tenant::query()->count())->toBe(0);
});

it('slugs the subdomain into the tenant id and domain', function () {
    $client = provision('Acme Corp');

    expect($client->tenant->id)->toBe('acme-corp')
        ->and($client->domain)->toBe('acme-corp.localhost');
});

it('cleanup() removes the tenant row, its domain and the orphan database file', function () {
    // The rollback path on a failed provision: build a tenant the way provision
    // does (row + domain + migrated DB file), then run the private cleanup and
    // assert nothing is left behind — including the .sqlite file.
    $tenant = Tenant::create(['id' => 'orphan', 'name' => 'Orphan']);
    $tenant->domains()->create(['domain' => 'orphan.localhost']);

    expect(is_file(database_path('tenantorphan.sqlite')))->toBeTrue();

    $service = new ClientProvisioningService;
    (new ReflectionMethod($service, 'cleanup'))->invoke($service, 'orphan');

    expect(Tenant::find('orphan'))->toBeNull()
        ->and(Domain::query()->where('domain', 'orphan.localhost')->exists())->toBeFalse()
        ->and(is_file(database_path('tenantorphan.sqlite')))->toBeFalse();
});
