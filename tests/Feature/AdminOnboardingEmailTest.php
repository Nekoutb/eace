<?php

declare(strict_types=1);

use App\Filament\Resources\Clients\Pages\ListClients;
use App\Mail\WorkspaceCredentialsMail;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each->delete();
    $file = database_path('tenantacme.sqlite');
    if (is_file($file)) {
        @unlink($file);
    }
});

it('emails the new client their credentials when an operator onboards them', function () {
    Mail::fake();
    config(['niu.multi_client' => true]);

    // A central (landlord) operator running the admin console.
    $operator = User::query()->create(['name' => 'Boss', 'email' => 'boss@central.test', 'password' => Hash::make('secret')]);
    $this->actingAs($operator);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListClients::class)
        ->callAction('provision', [
            'name' => 'Acme', 'admin_name' => 'Jane', 'admin_email' => 'jane@acme.test', 'allocated_space_mb' => 500,
        ]);

    expect(Tenant::find('acme'))->not->toBeNull();
    Mail::assertSent(WorkspaceCredentialsMail::class, fn (WorkspaceCredentialsMail $m) => $m->hasTo('jane@acme.test'));
});
