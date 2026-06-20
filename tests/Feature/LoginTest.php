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
