<?php

declare(strict_types=1);

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Auth\UserRole;
use App\Filament\App\Pages\ComplianceEngine;
use App\Models\AcfCertificate;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'stg']);
    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel('app');
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function actingRole(UserRole $role): User
{
    static $n = 0;
    $n++;
    $user = User::query()->create([
        'name' => "User {$n}",
        'email' => "user{$n}@stg.test",
        'password' => bcrypt('secret123'),
        'role' => $role,
    ]);
    test()->actingAs($user);

    return $user;
}

it('opens a recorder on the recording stage and refuses to switch to settlement', function () {
    actingRole(UserRole::Recorder);

    Livewire::test(ComplianceEngine::class)
        ->assertSet('stage', 'recording')
        ->call('setStage', 'settlement')
        ->assertSet('stage', 'recording');
});

it('opens a settler on the settlement stage and refuses to switch to recording', function () {
    actingRole(UserRole::Settler);

    Livewire::test(ComplianceEngine::class)
        ->assertSet('stage', 'settlement')
        ->call('setStage', 'recording')
        ->assertSet('stage', 'settlement');
});

it('lets an administrator use both stages', function () {
    actingRole(UserRole::Admin);

    Livewire::test(ComplianceEngine::class)
        ->assertSet('stage', 'recording')
        ->call('setStage', 'settlement')
        ->assertSet('stage', 'settlement')
        ->call('setStage', 'recording')
        ->assertSet('stage', 'recording');
});

it('refuses to run a stage the role does not cover, even if the property is forced', function () {
    actingRole(UserRole::Settler);

    // Force the recording stage past setStage, then run — the run() guard blocks it.
    Livewire::test(ComplianceEngine::class)
        ->set('stage', 'recording')
        ->set('rawInput', 'M058712345678X')
        ->call('run')
        ->assertSet('result', null);
});

it('phrases the settlement final decision with the payment wording, not the recording wording', function () {
    actingRole(UserRole::Settler);

    $rows = Livewire::test(ComplianceEngine::class)
        ->set('nius', 'M222222222222A')
        ->call('run')
        ->get('batch')['rows'];

    expect($rows[0]['conclusion']['decision'])
        ->toBeIn([__('app.ui.decision_pay'), __('app.ui.decision_no_pay')]);
});

it('records an accepted invoice with the recording wording for an admin', function () {
    actingRole(UserRole::Admin);

    $acf = new AcfCertificate([
        'reference' => '620000000099',
        'niu' => 'M058712345678X',
        'taxpayer_name' => 'SARL EXEMPLE',
        'issued_at' => now(),
        'validity_months' => 3,
        'authenticity_status' => AcfAuthenticityStatus::Authentic,
    ]);
    $acf->recomputeValidity()->save();

    Livewire::test(ComplianceEngine::class)
        ->set('rawInput', 'M058712345678X')
        ->call('run')
        ->assertSet('result.conclusion.payable', true)
        ->assertSet('result.conclusion.label', __('app.ui.conclusion_recordable'))
        ->assertSet('result.conclusion.decision', __('app.ui.decision_record'));
});
