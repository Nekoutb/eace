<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Exceptions\ReadOnlyImpersonationException;
use App\Models\DataAccessLog;
use App\Models\Tenant;
use App\Models\ThirdParty;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/*
 * Read-only tenant impersonation ("badge in"). These guarantees stay in CI: only a
 * privileged central admin with 2FA may badge in, the swap/restore is clean, every
 * start/stop is audited, and — the keystone — NO write can touch the client's
 * database while impersonating.
 */

afterEach(function () {
    Impersonation::stop();
    Tenant::all()->each->delete();
});

function centralAdminWithTotp(string $email = 'boss@central.test'): User
{
    $admin = User::query()->create(['name' => 'Boss', 'email' => $email, 'password' => Hash::make('x')]);
    $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save(); // enrol 2FA

    return $admin;
}

function workspaceWithAdmin(string $id = 'acme'): Tenant
{
    $tenant = Tenant::create(['id' => $id]);
    $tenant->run(function (): void {
        User::query()->create([
            'name' => 'Client Admin',
            'email' => 'admin@'.tenant()->getTenantKey().'.test',
            'password' => Hash::make('x'),
            'role' => UserRole::Admin->value,
        ]);
    });

    return $tenant;
}

it('lets only a privileged central admin with 2FA impersonate', function () {
    $admin = centralAdminWithTotp();
    expect($admin->canImpersonate())->toBeTrue();

    $noTotp = User::query()->create(['name' => 'NoTotp', 'email' => 'n@central.test', 'password' => Hash::make('x')]);
    expect($noTotp->canImpersonate())->toBeFalse();
});

it('does not let a tenant user impersonate', function () {
    $tenant = workspaceWithAdmin('beta');
    $tenant->run(function () use ($tenant): void {
        $client = User::query()->where('role', UserRole::Admin->value)->first();
        Auth::login($client);
        expect($client->canImpersonate())->toBeFalse()
            ->and(Impersonation::start($tenant))->toBeFalse();
    });
});

it('swaps to the workspace admin on start and restores the central admin on stop', function () {
    $admin = centralAdminWithTotp();
    $tenant = workspaceWithAdmin('gamma');
    Auth::login($admin);

    expect(Impersonation::isActive())->toBeFalse();
    expect(Impersonation::start($tenant))->toBeTrue();

    expect(Impersonation::isActive())->toBeTrue()
        ->and(Impersonation::readOnly())->toBeTrue()
        ->and(Auth::user()?->email)->toBe('admin@gamma.test')
        ->and(session('tenant_id'))->toBe($tenant->getTenantKey());

    Impersonation::stop();

    expect(Impersonation::isActive())->toBeFalse()
        ->and(Auth::user()?->email)->toBe('boss@central.test');
});

it('audits the start and end of impersonation', function () {
    $admin = centralAdminWithTotp();
    $tenant = workspaceWithAdmin('delta');
    Auth::login($admin);

    Impersonation::start($tenant);
    Impersonation::stop();

    $actions = DataAccessLog::query()->pluck('action')->all();
    expect($actions)->toContain('impersonate_start')
        ->and($actions)->toContain('impersonate_end');
});

it('moves the session password hash to the impersonated user so the next request is not logged out', function () {
    $admin = centralAdminWithTotp();
    $tenant = workspaceWithAdmin('zeta');
    Auth::login($admin);
    // The admin's /admin sign-in leaves their hash on the session.
    session()->put('password_hash_'.Auth::getDefaultDriver(), $admin->getAuthPassword());

    Impersonation::start($tenant);

    // After badge-in the hash matches the impersonated user — so AuthenticateSession
    // keeps the /app session alive instead of bouncing back to the sign-in page.
    $viewer = Auth::user();
    expect($viewer)->not->toBeNull()
        ->and(session('password_hash_'.Auth::getDefaultDriver()))->toBe($viewer->getAuthPassword());

    Impersonation::stop();

    // Exit restores the admin's hash.
    expect(session('password_hash_'.Auth::getDefaultDriver()))->toBe($admin->getAuthPassword());
});

it('blocks every write to the client database while impersonating, but allows reads', function () {
    $admin = centralAdminWithTotp();
    $tenant = workspaceWithAdmin('epsilon');
    Auth::login($admin);

    Impersonation::start($tenant);
    // The TenancyInitialized listener registers this on each real request once the
    // marker is set; do it here to exercise the guard within one test process.
    Impersonation::guard(DB::connection());

    // Reads are fine:
    expect(ThirdParty::query()->count())->toBeInt();

    // Writes are refused:
    expect(fn () => ThirdParty::query()->create(['name' => 'Sneaky', 'type' => 'supplier']))
        ->toThrow(ReadOnlyImpersonationException::class);

    // After exit, the same connection writes again (the guard re-checks at run time):
    Impersonation::stop();
    tenancy()->initialize($tenant);
    ThirdParty::query()->create(['name' => 'Allowed', 'type' => 'supplier']);
    expect(ThirdParty::query()->where('name', 'Allowed')->exists())->toBeTrue();
    tenancy()->end();
});
