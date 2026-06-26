<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\App\Resources\AcfCertificates\AcfCertificateResource;
use App\Filament\App\Resources\AuditLogs\AuditLogResource;
use App\Filament\App\Resources\TaxpayerFiles\TaxpayerFileResource;
use App\Filament\App\Resources\Verifications\VerificationResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/*
 * The platform-admin / client-portal boundary. These guarantees stay in CI
 * permanently: a client-portal (tenant) user must NEVER reach /admin, a platform
 * (central) user must never reach the client portal, and the bare domain must
 * always send people to the client portal. If any of these fail, the separation
 * the customer relies on is broken and nothing ships.
 */

afterEach(function () {
    Tenant::all()->each->delete(); // DeleteDatabase removes the tenant sqlite files
});

/** Create a tenant + a tenant (client) user, leaving tenancy initialised. */
function clientUserIn(string $id, UserRole $role = UserRole::Admin): User
{
    $tenant = Tenant::create(['id' => $id]);
    tenancy()->initialize($tenant);

    return User::query()->create([
        'name' => 'Client',
        'email' => 'client@'.$id.'.test',
        'password' => Hash::make('secret'),
        'role' => $role->value,
    ]);
}

/** @return list<class-string> the client-portal resources that must bar central users */
function clientPortalResources(): array
{
    return [
        AcfCertificateResource::class,
        AuditLogResource::class,
        TaxpayerFileResource::class,
        VerificationResource::class,
    ];
}

it('identifies central vs tenant users correctly', function () {
    $central = new User(['name' => 'Boss', 'email' => 'boss@central.test']);
    expect($central->isCentralUser())->toBeTrue();

    $client = clientUserIn('alpha');
    expect($client->isCentralUser())->toBeFalse();
    tenancy()->end();
});

it('enforces the panel-access matrix', function () {
    $admin = Filament::getPanel('admin');
    $app = Filament::getPanel('app');

    $central = User::query()->create([
        'name' => 'Boss', 'email' => 'boss@central.test', 'password' => Hash::make('x'),
    ]);
    expect($central->canAccessPanel($admin))->toBeTrue()
        ->and($central->canAccessPanel($app))->toBeFalse();

    $client = clientUserIn('beta');
    expect($client->canAccessPanel($app))->toBeTrue()
        ->and($client->canAccessPanel($admin))->toBeFalse();
    tenancy()->end();
});

it('bars central users from every client-portal resource', function () {
    $central = User::query()->create([
        'name' => 'Boss', 'email' => 'boss@central.test', 'password' => Hash::make('x'),
    ]);
    $this->actingAs($central);

    foreach (clientPortalResources() as $resource) {
        expect($resource::canAccess())->toBeFalse("{$resource} must bar central users");
    }

    // ...but a central user CAN reach the admin Clients resource.
    expect(ClientResource::canAccess())->toBeTrue();
});

it('admits tenant users to client-portal resources but not the admin Clients resource', function () {
    $client = clientUserIn('gamma');
    $this->actingAs($client);

    foreach (clientPortalResources() as $resource) {
        expect($resource::canAccess())->toBeTrue("{$resource} must admit tenant users");
    }

    expect(ClientResource::canAccess())->toBeFalse();
    tenancy()->end();
});

it('always redirects the bare domain to the client portal', function () {
    $this->get('/')->assertRedirect('/app');
});

it('bounces a signed-in client who opens /admin back to /app', function () {
    $client = clientUserIn('delta');
    tenancy()->end(); // the /admin request runs outside any tenant context

    $this->actingAs($client)->get('/admin')->assertRedirect('/app');
});

it('keeps the role column off the central users table (the isCentralUser linchpin)', function () {
    expect(Schema::hasColumn('users', 'role'))->toBeFalse();

    clientUserIn('epsilon');
    expect(Schema::hasColumn('users', 'role'))->toBeTrue(); // the tenant connection has it
    tenancy()->end();
});
