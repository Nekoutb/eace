<?php

declare(strict_types=1);

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
        'name' => 'Administrateur',
        'email' => 'admin',
        'password' => bcrypt('admin'),
    ]);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('authenticates with admin / admin through the custom identifier login', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'admin', 'password' => 'admin'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth()->check())->toBeTrue();
});

it('rejects a wrong password', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'admin', 'password' => 'nope'])
        ->call('authenticate')
        ->assertHasFormErrors();

    expect(auth()->check())->toBeFalse();
});
