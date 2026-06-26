<?php

declare(strict_types=1);

use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\ClientProvisioningService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each->delete();
    foreach (glob(database_path('tenant*.sqlite')) ?: [] as $file) {
        @unlink($file);
    }
});

it('lets an operator change a workspace storage quota to any value', function () {
    Mail::fake();
    config(['niu.multi_client' => true]);

    $operator = User::query()->create(['name' => 'Boss', 'email' => 'boss@central.test', 'password' => Hash::make('secret')]);
    $this->actingAs($operator);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $tenant = app(ClientProvisioningService::class)->provision(
        name: 'Acme', adminName: 'Jane', adminEmail: 'jane@acme.test', allocatedSpaceMb: 500,
    )->tenant;

    expect($tenant->allocatedSpaceMb())->toBe(500);

    Livewire::test(ListClients::class)
        ->callTableAction('setQuota', $tenant, data: ['allocated_space_mb' => 7777]);

    expect(Tenant::find('acme')?->allocatedSpaceMb())->toBe(7777);
});

it('persists an updated quota through the stancl data column', function () {
    $tenant = app(ClientProvisioningService::class)->provision(
        name: 'Beta', adminName: 'Bo', adminEmail: 'bo@beta.test', allocatedSpaceMb: 250,
    )->tenant;

    $tenant->update(['allocated_space_mb' => 123456]);

    expect(Tenant::find('beta')?->allocatedSpaceMb())->toBe(123456)
        ->and(Tenant::find('beta')?->name)->toBe('Beta'); // other data attributes preserved
});
