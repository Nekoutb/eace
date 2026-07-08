<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
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
    // Settings is admin-only, so the buyer-identity editor acts as an admin.
    $this->actingAs(User::query()->create([
        'name' => 'Owner',
        'email' => 'owner',
        'password' => bcrypt('secret'),
        'role' => UserRole::Admin,
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
        ->set('entityAddress', 'BP 5000, Douala')
        ->call('save')
        ->assertHasNoErrors();

    $profile = OrganizationProfile::current();

    expect($profile->entity_name)->toBe('Ma Société SARL')
        ->and($profile->entity_niu)->toBe('M020100021296Z')
        ->and($profile->entity_address)->toBe('BP 5000, Douala');
});

it('collects no buyer RCCM — not a legal invoice mention (CGI art. 150)', function () {
    // The settings page neither shows nor persists a buyer RCCM any more; a
    // legacy value already stored stays untouched but is never checked.
    Livewire::test(Settings::class)->assertOk()->assertDontSee(__('app.ui.entity_rccm'));
});

it('keeps a single profile row (singleton)', function () {
    OrganizationProfile::current();
    OrganizationProfile::current();

    expect(OrganizationProfile::query()->count())->toBe(1);
});
