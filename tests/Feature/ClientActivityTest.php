<?php

declare(strict_types=1);

use App\Filament\Pages\ClientActivity;
use App\Filament\Pages\ClientActivity\ActivityChart;
use App\Filament\Pages\ClientActivity\ClientActivityTable;
use App\Models\AcfCertificate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Activity\ClientActivityService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/*
 * The operator's "Client activity" section: daily volumes (invoices recorded,
 * payments checked, ACFs processed) aggregated ACROSS tenant databases, per client
 * or combined — the data behind proactive account care.
 */

afterEach(function () {
    Tenant::all()->each->delete();
});

/** Insert a verification row in the CURRENT tenant, at a given day offset. */
function activityVerification(string $stage, int $daysAgo): void
{
    $at = now()->subDays($daysAgo);
    DB::table('verifications')->insert([
        'raw_input' => 'M058712345678X',
        'niu' => 'M058712345678X',
        'verdict' => 'conforme',
        'stage' => $stage,
        'exposure_xaf' => 0,
        'created_at' => $at,
        'updated_at' => $at,
    ]);
}

/** Create an ACF certificate in the CURRENT tenant, at a given day offset. */
function activityAcf(string $reference, int $daysAgo): void
{
    $acf = new AcfCertificate([
        'reference' => $reference,
        'niu' => 'M058712345678X',
        'issued_at' => now()->subDays($daysAgo),
        'validity_months' => 3,
    ]);
    $acf->recomputeValidity()->save();
    $acf->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();
}

it('builds the daily series for one client and for all clients combined', function () {
    $alpha = Tenant::create(['id' => 'act-alpha', 'name' => 'Alpha SARL']);
    $beta = Tenant::create(['id' => 'act-beta', 'name' => 'Beta SA']);

    tenancy()->initialize($alpha);
    activityVerification('recording', 0);
    activityVerification('recording', 0);
    activityVerification('settlement', 1);
    activityAcf('620100000001', 0);
    tenancy()->end();

    tenancy()->initialize($beta);
    activityVerification('recording', 0);
    tenancy()->end();

    $service = app(ClientActivityService::class);

    $alphaSeries = $service->dailySeries('act-alpha', 7);
    expect($alphaSeries['labels'])->toHaveCount(7)
        ->and(end($alphaSeries['recorded']))->toBe(2)     // today: two invoices recorded
        ->and($alphaSeries['settled'][5])->toBe(1)         // yesterday: one settlement
        ->and(end($alphaSeries['acfs']))->toBe(1);         // today: one ACF

    $all = $service->dailySeries(null, 7);
    expect(end($all['recorded']))->toBe(3);                // alpha (2) + beta (1)
});

it('summarises a client for outreach: 30-day volumes and last activity', function () {
    $tenant = Tenant::create(['id' => 'act-gamma', 'name' => 'Gamma SARL']);

    tenancy()->initialize($tenant);
    activityVerification('recording', 2);
    activityVerification('settlement', 40); // outside the 30-day window
    activityAcf('620100000002', 2);
    tenancy()->end();

    $summary = app(ClientActivityService::class)->clientSummary($tenant);

    expect($summary['recorded30'])->toBe(1)
        ->and($summary['settled30'])->toBe(0)              // 40 days ago — out of window
        ->and($summary['acfs30'])->toBe(1)
        ->and($summary['total30'])->toBe(2)
        ->and($summary['last'])->not->toBeNull()
        ->and($summary['last']->isSameDay(now()->subDays(2)))->toBeTrue();
});

it('admits only central users to the Client activity page', function () {
    $tenant = Tenant::create(['id' => 'act-user']);
    tenancy()->initialize($tenant);
    $client = User::query()->create(['name' => 'C', 'email' => 'c@t.test', 'password' => Hash::make('x'), 'role' => 'admin']);
    $this->actingAs($client);
    expect(ClientActivity::canAccess())->toBeFalse();
    tenancy()->end();

    $central = User::query()->create(['name' => 'Boss', 'email' => 'boss@central.test', 'password' => Hash::make('x')]);
    $this->actingAs($central);
    expect(ClientActivity::canAccess())->toBeTrue();
});

it('renders the page, the chart and the outreach table for an operator', function () {
    $tenant = Tenant::create(['id' => 'act-render', 'name' => 'Render SARL']);
    tenancy()->initialize($tenant);
    activityVerification('recording', 0);
    tenancy()->end();

    $central = User::query()->create(['name' => 'Boss', 'email' => 'boss@central.test', 'password' => Hash::make('x')]);
    $this->actingAs($central);
    Filament::setCurrentPanel('admin');

    Livewire\Livewire::test(ClientActivity::class)->assertOk();
    Livewire\Livewire::test(ActivityChart::class)->assertOk();
    Livewire\Livewire::test(ClientActivityTable::class)
        ->assertOk()
        ->assertSee('Render SARL');
});

// Runs LAST: simulates the real prod incident (a workspace's sqlite file cleaned
// away) without mutating schema through the shared test connection.
it('treats a broken tenant database as zero activity, never an error', function () {
    $tenant = Tenant::create(['id' => 'act-broken']);
    $path = database_path('tenantact-broken.sqlite');
    @unlink($path); // the workspace database file disappears

    $summary = app(ClientActivityService::class)->clientSummary($tenant);

    expect($summary['total30'])->toBe(0)->and($summary['last'])->toBeNull();

    touch($path); // leave a file for the afterEach tenant cleanup to remove
});
