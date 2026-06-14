<?php

declare(strict_types=1);

use App\Filament\App\Pages\Settings;
use App\Models\OrganizationProfile;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'profile']);
    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel('app');
    $this->actingAs(User::query()->create([
        'name' => 'Owner',
        'email' => 'owner',
        'password' => bcrypt('secret'),
    ]));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('saves the organisation buyer identity through the settings page', function () {
    Livewire::test(Settings::class)
        ->set('entityName', 'Ma Société SARL')
        ->set('entityNiu', 'm020100021296z') // lower-case → stored upper-cased
        ->call('save')
        ->assertHasNoErrors();

    $profile = OrganizationProfile::current();

    expect($profile->entity_name)->toBe('Ma Société SARL')
        ->and($profile->entity_niu)->toBe('M020100021296Z');
});

it('keeps a single profile row (singleton)', function () {
    OrganizationProfile::current();
    OrganizationProfile::current();

    expect(OrganizationProfile::query()->count())->toBe(1);
});
