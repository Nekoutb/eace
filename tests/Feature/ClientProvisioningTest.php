<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Models\Tenant;
use App\Models\TenantLogin;
use App\Models\User;
use App\Services\Tenant\ClientProvisioningException;
use App\Services\Tenant\ClientProvisioningService;
use App\Services\Tenant\ProvisionedClient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each->delete();
    foreach (['acme-trading-sarl', 'beta-trials', 'acme', 'acme-corp', 'orphan', 'gamma-ltd', 'trial-co'] as $id) {
        $file = database_path('tenant'.$id.'.sqlite');
        if (is_file($file)) {
            @unlink($file);
        }
    }
});

function provision(string $name = 'Acme Trading SARL', ?string $email = null): ProvisionedClient
{
    return app(ClientProvisioningService::class)->provision(
        name: $name,
        adminName: 'Jane Admin',
        adminEmail: $email ?? 'jane@'.Str::slug($name).'.test',
    );
}

it('provisions an isolated client with an admin carrying a working temporary password', function () {
    $client = provision('Acme Trading SARL');

    expect($client->tenant->id)->toBe('acme-trading-sarl')
        ->and($client->tenant->name)->toBe('Acme Trading SARL')
        ->and($client->loginUrl)->toEndWith('/app/login')
        ->and(strlen($client->temporaryPassword))->toBe(12);

    $client->tenant->run(function () use ($client) {
        $admin = User::query()->sole();

        expect($admin->email)->toBe('jane@acme-trading-sarl.test')
            ->and($admin->role)->toBe(UserRole::Admin)
            ->and($admin->must_change_password)->toBeTrue()
            ->and(Hash::check($client->temporaryPassword, $admin->password))->toBeTrue();
    });
});

it('registers each admin in the central login directory (email → workspace)', function () {
    provision('Acme Trading SARL', 'jane@acme.test');
    provision('Beta Trials', 'bea@beta.test');

    expect(TenantLogin::tenantFor('jane@acme.test'))->toBe('acme-trading-sarl')
        ->and(TenantLogin::tenantFor('bea@beta.test'))->toBe('beta-trials')
        ->and(TenantLogin::tenantFor('nobody@x.test'))->toBeNull();
});

it('keeps each client database isolated from the others', function () {
    provision('Acme Trading SARL', 'jane@acme.test');
    provision('Beta Trials', 'bea@beta.test');

    Tenant::find('acme-trading-sarl')->run(fn () => expect(User::query()->pluck('email')->all())->toBe(['jane@acme.test']));
    Tenant::find('beta-trials')->run(fn () => expect(User::query()->pluck('email')->all())->toBe(['bea@beta.test']));
});

it('keeps the directory in sync when a tenant user is added or removed', function () {
    $client = provision('Acme Trading SARL', 'jane@acme.test');

    $client->tenant->run(function () {
        User::query()->create([
            'name' => 'Clerk', 'email' => 'clerk@acme.test',
            'password' => Hash::make('x'), 'role' => UserRole::Recorder,
        ]);
    });

    expect(TenantLogin::tenantFor('clerk@acme.test'))->toBe('acme-trading-sarl');

    $client->tenant->run(fn () => User::query()->where('email', 'clerk@acme.test')->first()->delete());

    expect(TenantLogin::tenantFor('clerk@acme.test'))->toBeNull();
});

it('does not let one workspace hijack an email already registered to another', function () {
    // Acme owns the email; Beta then adds a user with the same email. The central
    // directory must keep pointing the email at Acme — no cross-tenant takeover.
    provision('Acme Trading SARL', 'shared@x.test');
    $beta = provision('Beta Trials', 'bea@beta.test');

    $beta->tenant->run(function () {
        User::query()->create([
            'name' => 'Intruder', 'email' => 'shared@x.test',
            'password' => Hash::make('x'), 'role' => UserRole::Recorder,
        ]);
    });

    expect(TenantLogin::tenantFor('shared@x.test'))->toBe('acme-trading-sarl');
});

it('scopes directory removal to the deleting workspace', function () {
    provision('Acme Trading SARL', 'shared@x.test');
    $beta = provision('Beta Trials', 'bea@beta.test');

    $beta->tenant->run(function () {
        $intruder = User::query()->create([
            'name' => 'Intruder', 'email' => 'shared@x.test',
            'password' => Hash::make('x'), 'role' => UserRole::Recorder,
        ]);
        $intruder->delete();
    });

    // Acme's directory entry survives Beta deleting its same-named user.
    expect(TenantLogin::tenantFor('shared@x.test'))->toBe('acme-trading-sarl');
});

it('rejects an email already in use by another workspace', function () {
    provision('Acme Trading SARL', 'shared@x.test');

    expect(fn () => provision('Beta Trials', 'shared@x.test'))
        ->toThrow(ClientProvisioningException::class);

    // The failed second provision left nothing behind.
    expect(Tenant::find('beta-trials'))->toBeNull();
});

it('rejects a blank organisation name into the reserved-safe id, never a reserved label', function () {
    // A name slugging to a reserved label is prefixed, never used verbatim.
    $client = app(ClientProvisioningService::class)->provision(
        name: 'admin', adminName: 'A', adminEmail: 'a@x.test',
    );

    expect($client->tenant->id)->toBe('client-admin');
});

it('derives a unique workspace id from the organisation name', function () {
    $first = provision('Acme Corp', 'a1@x.test');
    $second = provision('Acme Corp', 'a2@x.test');

    expect($first->tenant->id)->toBe('acme-corp')
        ->and($second->tenant->id)->toBe('acme-corp-2');
});

it('records the allocated storage quota on the workspace', function () {
    $client = app(ClientProvisioningService::class)->provision(
        name: 'Acme', adminName: 'A', adminEmail: 'a@acme.test', allocatedSpaceMb: 750,
    );

    expect((int) $client->tenant->allocated_space_mb)->toBe(750)
        ->and($client->tenant->fresh()->allocatedSpaceMb())->toBe(750);
});

it('provisions with the given role and the chosen password, and no temporary one', function () {
    $client = app(ClientProvisioningService::class)->provision(
        name: 'Beta Trials', adminName: 'Bea', adminEmail: 'bea@beta.test',
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
        'name' => 'Gina',
        'email' => 'gina@gamma.test',
        'password' => 'trialpass123',
        'password_confirmation' => 'trialpass123',
    ]);

    $response->assertRedirect();
    expect(Tenant::find('gamma-ltd'))->not->toBeNull()
        ->and(TenantLogin::tenantFor('gina@gamma.test'))->toBe('gamma-ltd');

    Tenant::find('gamma-ltd')->run(function () {
        $user = User::query()->sole();
        expect($user->email)->toBe('gina@gamma.test')
            ->and($user->role)->toBe(UserRole::Admin);
    });
});

it('rejects a self-service sign-up with a mismatched password confirmation', function () {
    $this->post('/register', [
        'org_name' => 'Trial Co', 'name' => 'T',
        'email' => 't@trial.test', 'password' => 'abcdefgh1', 'password_confirmation' => 'different1',
    ])->assertSessionHasErrors('password');

    expect(Tenant::find('trial-co'))->toBeNull();
});
