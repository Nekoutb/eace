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
    $this->tenant = Tenant::create(['id' => 'rpt']);
    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel('app');

    $user = User::query()->create([
        'name' => 'Settler',
        'email' => 'settler@rpt.test',
        'password' => bcrypt('secret123'),
        'role' => UserRole::Settler,
    ]);
    $this->actingAs($user);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** A cleanly valid, authentic ACF for the fake register's clean NIU. */
function seedValidAcf(): AcfCertificate
{
    $acf = new AcfCertificate([
        'reference' => '620000000042',
        'niu' => 'M058712345678X',
        'taxpayer_name' => 'SARL EXEMPLE',
        'issued_at' => now(),
        'validity_months' => 3,
        'authenticity_status' => AcfAuthenticityStatus::Authentic,
    ]);

    $acf->recomputeValidity()->save();

    return $acf;
}

it('shows the vendor name from the register on each settlement row', function () {
    seedValidAcf();

    $rows = Livewire::test(ComplianceEngine::class)
        ->set('nius', 'M058712345678X')
        ->call('run')
        ->get('batch')['rows'];

    expect($rows[0]['vendor_name'])->toBe('SARL EXEMPLE');
});

it('presents an absent certificate neutrally at settlement — not as a failure', function () {
    // Clean NIU, but NO certificate in the register.
    $rows = Livewire::test(ComplianceEngine::class)
        ->set('nius', 'M058712345678X')
        ->call('run')
        ->get('batch')['rows'];

    $row = $rows[0];
    $acfCheck = $row['checks_by_key']['acf_validity'];

    expect($acfCheck['passed'])->toBeNull()
        ->and($row['verdict']['label'])->toBe(__('app.verdict.not_performed'))
        ->and($row['verdict']['color'])->toBe('warning')
        ->and($row['conclusion']['color'])->toBe('warning')
        ->and($row['conclusion']['decision'])->toBe(__('app.ui.decision_no_acf'))
        ->and($row['conclusion']['label'])->toBe(__('app.ui.conclusion_no_acf'))
        // Neutral is never an approval.
        ->and($row['conclusion']['payable'])->toBeFalse();
});

it('downloads the settlement report as CSV with vendor names and decisions', function () {
    seedValidAcf();

    $component = Livewire::test(ComplianceEngine::class)
        ->set('nius', "M058712345678X\nM999999999999Z")
        ->call('run');

    $component->call('exportSettlementReport')->assertFileDownloaded();

    // Livewire stores the streamed download base64-encoded in the effects.
    $csv = base64_decode((string) data_get($component->effects, 'download.content'));

    expect($csv)->toContain('M058712345678X')
        ->toContain('SARL EXEMPLE')
        ->toContain((string) __('app.ui.decision_pay'));
});
