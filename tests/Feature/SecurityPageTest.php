<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\App\Pages\Security;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'sec']);
    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel('app');
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('shows the in-app security page with the certification strip to any role', function () {
    $recorder = User::query()->create([
        'name' => 'Clerk', 'email' => 'clerk@sec.test',
        'password' => Hash::make('secret123'), 'role' => UserRole::Recorder,
    ]);
    test()->actingAs($recorder);

    expect(Security::canAccess())->toBeTrue();

    Livewire::test(Security::class)
        ->assertOk()
        ->assertSee('ISO/IEC 27001:2022')
        ->assertSee('SOC 2 Type II')
        ->assertSee('PCI-DSS');
});
