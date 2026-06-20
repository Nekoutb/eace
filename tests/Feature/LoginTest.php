<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\Auth\Login;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'auth']);
    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel('app');
    User::query()->create([
        'name' => 'Clerk',
        'email' => 'clerk@auth.test',
        'password' => bcrypt('secret123'),
        'role' => UserRole::Admin,
    ]);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('authenticates a workspace user by their email on the customer panel', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'clerk@auth.test', 'password' => 'secret123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth()->check())->toBeTrue();
});

it('rejects the workspace identifier typed in place of the email', function () {
    // The /app sign-in is by email — typing the workspace name must fail cleanly.
    Livewire::test(Login::class)
        ->fillForm(['email' => 'auth', 'password' => 'secret123'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth()->check())->toBeFalse();
});

it('rejects a wrong password', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'clerk@auth.test', 'password' => 'nope'])
        ->call('authenticate')
        ->assertHasFormErrors();

    expect(auth()->check())->toBeFalse();
});

it('pre-fills the sign-in email from the welcome-link query parameter', function () {
    $this->get('/app/login?email='.urlencode('clerk@auth.test'))
        ->assertOk()
        ->assertSee('clerk@auth.test', escape: false);
});

it('shows the customer sign-in form to a signed-in landlord, instead of bouncing them', function () {
    // A landlord (central user, no role) follows a client's welcome-email link to
    // /app/login while still signed in to /admin — they must see the form so they
    // can sign in as the client, not be redirected back to their own panel.
    test()->actingAs(new User(['name' => 'Landlord']));

    Livewire::test(Login::class)->assertNoRedirect();
});
