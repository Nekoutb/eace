<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\App\Pages\MyData;
use App\Filament\Pages\RecordOfProcessing;
use App\Filament\Resources\DataBreaches\DataBreachResource;
use App\Filament\Resources\DataRequests\DataRequestResource;
use App\Models\DataAccessLog;
use App\Models\DataBreach;
use App\Models\DataRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DataProtection\DataSubjectService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each->delete();
    DataAccessLog::query()->delete();
    DataRequest::query()->delete();
});

it('records a read-level access-log entry', function () {
    DataAccessLog::record('export', 'own-data', 'test');

    $log = DataAccessLog::query()->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('export')
        ->and($log->resource)->toBe('own-data');
});

it('exports the user’s own data and lodges a request', function () {
    $tenant = Tenant::create(['id' => 'dp']);
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel('app');
    $user = User::query()->create(['name' => 'Dee', 'email' => 'dee@dp.test', 'password' => Hash::make('secret123'), 'role' => UserRole::Admin]);
    test()->actingAs($user);

    $export = app(DataSubjectService::class)->exportFor($user);
    expect($export['account']['email'])->toBe('dee@dp.test')
        ->and($export['activity'])->toBeArray();

    $req = app(DataSubjectService::class)->lodgeRequest($user, 'erasure', 'please remove me');
    expect($req->type)->toBe('erasure')
        ->and($req->status)->toBe('new')
        ->and($req->requester_email)->toBe('dee@dp.test')
        ->and($req->due_at)->not->toBeNull();
});

it('renders the in-app My data page for a workspace user', function () {
    app()->setLocale('en');
    $tenant = Tenant::create(['id' => 'dp2']);
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel('app');
    $user = User::query()->create(['name' => 'D', 'email' => 'd@dp2.test', 'password' => Hash::make('secret123'), 'role' => UserRole::Recorder]);
    test()->actingAs($user);

    Livewire::test(MyData::class)->assertOk()->assertSee(__('dp.my.export_title'));
});

it('renders the Record of Processing and confines DP resources to central staff', function () {
    app()->setLocale('en');
    $owner = User::query()->create(['name' => 'O', 'email' => 'owner@dp.test', 'password' => Hash::make('secret123')]);
    test()->actingAs($owner);
    Filament::setCurrentPanel('admin');

    expect($owner->isCentralUser())->toBeTrue()
        ->and(DataBreachResource::canAccess())->toBeTrue()
        ->and(DataRequestResource::canAccess())->toBeTrue();

    Livewire::test(RecordOfProcessing::class)->assertOk()->assertSee('Supplier compliance verification');
});

it('records a breach and marks the Authority notified', function () {
    $breach = DataBreach::query()->create([
        'detected_at' => now(), 'description' => 'lost a laptop', 'risk' => 'high', 'status' => 'open',
    ]);

    expect($breach->authority_notified_at)->toBeNull();

    $breach->forceFill(['authority_notified_at' => now()])->save();

    expect($breach->fresh()->authority_notified_at)->not->toBeNull();
});

it('reports retention posture and rotates aged access logs', function () {
    DataAccessLog::query()->create(['action' => 'export', 'resource' => 'old', 'created_at' => now()->subYears(3)]);
    DataAccessLog::query()->create(['action' => 'export', 'resource' => 'new', 'created_at' => now()]);

    test()->artisan('data:retention --prune')->assertSuccessful();

    expect(DataAccessLog::query()->count())->toBe(1); // the 3-year-old entry rotated out
});
