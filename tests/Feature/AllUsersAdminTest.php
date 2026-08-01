<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\App\Resources\Users\Pages\ListUsers;
use App\Filament\Pages\AllUsers;
use App\Mail\WorkspaceCredentialsMail;
use App\Models\Tenant;
use App\Models\TenantLogin;
use App\Models\User;
use App\Services\Tenant\WorkspaceUserDirectory;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * Stream B — user administration: the operator's cross-workspace "All users"
 * view (list / resend credentials / delete, last-admin guarded) and the fix for
 * silent in-app onboarding: adding a user (or resetting their password) EMAILS
 * the credentials to the user themselves so onboarding materialises.
 */

beforeEach(fn () => Mail::fake());

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** A workspace with an admin + a recorder; returns the tenant. */
function usersWorkspace(string $id, string $adminEmail, ?string $recorderEmail = null): Tenant
{
    $tenant = Tenant::create(['id' => $id, 'name' => strtoupper($id)]);
    tenancy()->initialize($tenant);
    User::query()->create(['name' => 'Admin '.$id, 'email' => $adminEmail, 'password' => Hash::make('x'), 'role' => UserRole::Admin]);
    if ($recorderEmail !== null) {
        User::query()->create(['name' => 'Rec '.$id, 'email' => $recorderEmail, 'password' => Hash::make('x'), 'role' => UserRole::Recorder]);
    }
    tenancy()->end();

    return $tenant;
}

it('lists every user across every workspace with their flags', function () {
    usersWorkspace('au-a', 'admin@a.test', 'rec@a.test');
    usersWorkspace('au-b', 'admin@b.test');

    $rows = app(WorkspaceUserDirectory::class)->all();

    expect($rows)->toHaveCount(3)
        ->and(collect($rows)->pluck('email'))->toContain('admin@a.test', 'rec@a.test', 'admin@b.test');

    $adminA = collect($rows)->firstWhere('email', 'admin@a.test');
    $recA = collect($rows)->firstWhere('email', 'rec@a.test');
    expect($adminA['is_last_admin'])->toBeTrue()   // only admin of au-a
        ->and($recA['is_last_admin'])->toBeFalse()
        ->and($adminA['tenant_id'])->toBe('au-a');
});

it('resends credentials: new one-time password, forced change, emailed to the user', function () {
    $tenant = usersWorkspace('au-a', 'admin@a.test', 'rec@a.test');
    $userId = (int) $tenant->run(fn () => User::query()->where('email', 'rec@a.test')->value('id'));

    $temp = app(WorkspaceUserDirectory::class)->resendCredentials('au-a', $userId);

    expect(strlen($temp))->toBeGreaterThanOrEqual(10);
    Mail::assertQueued(WorkspaceCredentialsMail::class, fn (WorkspaceCredentialsMail $m) => $m->hasTo('rec@a.test'));

    $tenant->run(function () use ($temp) {
        $user = User::query()->where('email', 'rec@a.test')->first();
        expect(Hash::check($temp, (string) $user->password))->toBeTrue()
            ->and($user->must_change_password)->toBeTrue();
    });
});

it('deletes a user and their directory entry from the admin console', function () {
    $tenant = usersWorkspace('au-a', 'admin@a.test', 'rec@a.test');
    $userId = (int) $tenant->run(fn () => User::query()->where('email', 'rec@a.test')->value('id'));

    app(WorkspaceUserDirectory::class)->deleteUser('au-a', $userId);

    expect($tenant->run(fn () => User::query()->where('email', 'rec@a.test')->exists()))->toBeFalse()
        ->and(TenantLogin::tenantFor('rec@a.test'))->toBeNull();
});

it('refuses to delete a workspace\'s last administrator, even as the operator', function () {
    $tenant = usersWorkspace('au-a', 'admin@a.test');
    $adminId = (int) $tenant->run(fn () => User::query()->where('email', 'admin@a.test')->value('id'));

    expect(fn () => app(WorkspaceUserDirectory::class)->deleteUser('au-a', $adminId))
        ->toThrow(RuntimeException::class);

    expect($tenant->run(fn () => User::query()->where('email', 'admin@a.test')->exists()))->toBeTrue();
});

it('admits only central users to the All users page and renders the rows', function () {
    usersWorkspace('au-a', 'admin@a.test');

    // A tenant user is barred.
    tenancy()->initialize(Tenant::find('au-a'));
    $client = User::query()->where('email', 'admin@a.test')->firstOrFail();
    $this->actingAs($client);
    expect(AllUsers::canAccess())->toBeFalse();
    tenancy()->end();

    // The operator sees every workspace's users.
    $central = User::query()->create(['name' => 'Boss', 'email' => 'boss@central.test', 'password' => Hash::make('x')]);
    $this->actingAs($central);
    Filament::setCurrentPanel('admin');

    Livewire::test(AllUsers::class)
        ->assertOk()
        ->assertSee('admin@a.test');
});

it('emails the credentials when a workspace admin ADDS a user in-app', function () {
    $tenant = usersWorkspace('au-a', 'admin@a.test');
    tenancy()->initialize($tenant);
    $this->actingAs(User::query()->where('email', 'admin@a.test')->firstOrFail());
    Filament::setCurrentPanel('app');

    Livewire::test(ListUsers::class)
        ->callAction('addUser', [
            'name' => 'Nouvelle Recrue',
            'email' => 'recrue@a.test',
            'role' => UserRole::Recorder->value,
        ])
        ->assertHasNoActionErrors();

    // The NEW USER received their onboarding email (credentials + sign-in button).
    Mail::assertQueued(WorkspaceCredentialsMail::class, fn (WorkspaceCredentialsMail $m) => $m->hasTo('recrue@a.test'));

    expect(User::query()->where('email', 'recrue@a.test')->exists())->toBeTrue();
});

it('emails the new one-time password when a workspace admin RESETS a user', function () {
    $tenant = usersWorkspace('au-a', 'admin@a.test', 'rec@a.test');
    tenancy()->initialize($tenant);
    $this->actingAs(User::query()->where('email', 'admin@a.test')->firstOrFail());
    Filament::setCurrentPanel('app');

    $record = User::query()->where('email', 'rec@a.test')->firstOrFail();

    Livewire::test(ListUsers::class)
        ->callTableAction('resetPassword', $record)
        ->assertHasNoTableActionErrors();

    Mail::assertQueued(WorkspaceCredentialsMail::class, fn (WorkspaceCredentialsMail $m) => $m->hasTo('rec@a.test'));
});

/*
 * Phone add/edit from the admin console + inactive-customer surfacing.
 */

it('lets the operator set and clear a user phone from the admin console', function () {
    $tenant = usersWorkspace('au-a', 'admin@a.test');
    $userId = $tenant->run(fn () => User::query()->where('email', 'admin@a.test')->value('id'));

    app(WorkspaceUserDirectory::class)->updatePhone('au-a', (int) $userId, '  +237 677 33 22 89  ');
    expect($tenant->run(fn () => User::query()->find($userId)->phone))->toBe('+237 677 33 22 89'); // trimmed

    // An empty value clears it back to null.
    app(WorkspaceUserDirectory::class)->updatePhone('au-a', (int) $userId, '   ');
    expect($tenant->run(fn () => User::query()->find($userId)->phone))->toBeNull();
});

it('flags users who have gone quiet beyond the working-day threshold', function () {
    $tenant = usersWorkspace('au-a', 'active@a.test', 'quiet@a.test');
    tenancy()->initialize($tenant);
    // Active user signed in today; quiet user signed in 3 weeks ago; a third never.
    User::query()->where('email', 'active@a.test')->update(['last_login_at' => now()]);
    User::query()->where('email', 'quiet@a.test')->update(['last_login_at' => now()->subWeeks(3)]);
    User::query()->create(['name' => 'Never', 'email' => 'never@a.test', 'password' => Hash::make('x'), 'role' => UserRole::Recorder]);
    tenancy()->end();

    $rows = collect(app(WorkspaceUserDirectory::class)->all())->keyBy('email');

    expect($rows['active@a.test']['inactive'])->toBeFalse()
        ->and($rows['quiet@a.test']['inactive'])->toBeTrue()
        ->and($rows['quiet@a.test']['idle_working_days'])->toBeGreaterThan(5)
        ->and($rows['never@a.test']['inactive'])->toBeTrue()          // never signed in
        ->and($rows['never@a.test']['idle_working_days'])->toBeNull();
});

it('filters the All-users page to only inactive customers', function () {
    $tenant = usersWorkspace('au-a', 'active@a.test', 'quiet@a.test');
    tenancy()->initialize($tenant);
    User::query()->where('email', 'active@a.test')->update(['last_login_at' => now()]);
    User::query()->where('email', 'quiet@a.test')->update(['last_login_at' => now()->subWeeks(3)]);
    tenancy()->end();

    $central = User::query()->create(['name' => 'Boss', 'email' => 'boss@central.test', 'password' => Hash::make('x')]);
    $this->actingAs($central);
    Filament::setCurrentPanel('admin');

    Livewire::test(AllUsers::class)
        ->assertSee('active@a.test')
        ->set('onlyInactive', true)
        ->assertSee('quiet@a.test')
        ->assertDontSee('active@a.test');
});
