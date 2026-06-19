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
    foreach (['acme', 'beta', 'acme-corp', 'orphan', 'gamma', 'trialco'] as $id) {
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

it('records the allocated storage quota on the workspace', function () {
    $client = app(ClientProvisioningService::class)->provision(
        name: 'Acme', subdomain: 'acme', adminName: 'A', adminEmail: 'a@acme.test',
        allocatedSpaceMb: 750,
    );

    expect((int) $client->tenant->allocated_space_mb)->toBe(750)
        ->and($client->tenant->allocatedSpaceMb())->toBe(750)
        ->and($client->tenant->fresh()->allocatedSpaceMb())->toBe(750);
});

it('provisions with the given role and the chosen password, and no temporary one', function () {
    $client = app(ClientProvisioningService::class)->provision(
        name: 'Beta Trials', subdomain: 'beta', adminName: 'Bea', adminEmail: 'bea@beta.test',
        allocatedSpaceMb: 250, password: 'mychosenpass1', role: UserRole::Recorder,
    );

    expect($client->temporaryPassword)->toBeNull();

    $client->tenant->run(function () {
        $user = User::query()->sole();
        expect($user->role)->toBe(UserRole::Recorder)
            ->and($user->must_change_password)->toBeFalse()
            ->and(Hash::check('mychosenpass1', $user->password))->toBeTrue();
    });
});

it('lets a customer self-provision a trial workspace from the public sign-up', function () {
    $response = $this->post('/register', [
        'org_name' => 'Gamma Ltd',
        'subdomain' => 'gamma',
        'name' => 'Gina',
        'email' => 'gina@gamma.test',
        'password' => 'trialpass123',
        'password_confirmation' => 'trialpass123',
    ]);

    $response->assertRedirect();
    expect(Tenant::find('gamma'))->not->toBeNull();

    Tenant::find('gamma')->run(function () {
        $user = User::query()->sole();
        expect($user->email)->toBe('gina@gamma.test')
            // The self-service owner lands as the workspace Administrator.
            ->and($user->role)->toBe(UserRole::Admin);
    });
});

it('rejects a self-service sign-up with a mismatched password confirmation', function () {
    $this->post('/register', [
        'org_name' => 'Trial Co', 'subdomain' => 'trialco', 'name' => 'T',
        'email' => 't@trial.test', 'password' => 'abcdefgh1', 'password_confirmation' => 'different1',
    ])->assertSessionHasErrors('password');

    expect(Tenant::find('trialco'))->toBeNull();
});
