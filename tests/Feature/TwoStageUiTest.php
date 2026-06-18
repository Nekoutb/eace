<?php

declare(strict_types=1);

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Domain\Fiscalis\FiscalisPresence;
use App\Filament\App\Pages\ComplianceEngine;
use App\Models\AcfCertificate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verification;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'ui']);
    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel('app');
    $this->actingAs(User::query()->create([
        'name' => 'Agent',
        'email' => 'agent',
        'password' => bcrypt('secret'),
    ]));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function uiAuthenticAcf(): AcfCertificate
{
    $acf = new AcfCertificate([
        'reference' => '999000111222',
        'niu' => 'M058712345678X',
        'taxpayer_name' => 'SARL EXEMPLE',
        'issued_at' => now(),
        'validity_months' => 3,
        'authenticity_status' => AcfAuthenticityStatus::Authentic,
    ]);
    $acf->recomputeValidity()->save();

    return $acf;
}

it('records an accepted verification inline through the recording phase', function () {
    uiAuthenticAcf();

    Livewire::test(ComplianceEngine::class)
        ->set('rawInput', 'M058712345678X')
        ->call('run')
        ->assertSet('result.approval.accepted', true)
        ->assertSet('result.verdict.label', __('app.verdict.conforme'))
        ->assertSet('result.conclusion.payable', true)
        ->assertSee(__('app.ui.results'))
        ->assertSee(__('app.ui.section_remediation'));

    $verification = Verification::query()->where('stage', VerificationStage::Recording->value)->first();

    expect($verification)->not->toBeNull()
        ->and($verification->approval)->toBe(ApprovalDecision::Accepted);
});

it('rejects a recording with neither an ACF nor a NIU', function () {
    Livewire::test(ComplianceEngine::class)
        ->call('run');

    expect(Verification::query()->count())->toBe(0);
});

it('attaches the live Fiscalis snapshot captured at scan-time to the recording verification', function () {
    // Mirrors the page: the snapshot is captured when the invoice is scanned and
    // stashed on the component, then persisted onto the verification by run().
    Livewire::test(ComplianceEngine::class)
        ->set('rawInput', 'M058712345678X')
        ->set('fiscalisPresence', 'present')
        ->set('fiscalisSnapshotPath', 'fiscalis-snapshots/niu-test.png')
        ->set('fiscalisCheckedAt', now()->toDateTimeString())
        ->call('run');

    $verification = Verification::query()->where('stage', VerificationStage::Recording->value)->first();

    expect($verification->fiscalis_presence)->toBe(FiscalisPresence::Present)
        ->and($verification->fiscalis_snapshot_path)->toBe('fiscalis-snapshots/niu-test.png')
        ->and($verification->fiscalis_checked_at)->not->toBeNull();
});

it('runs a batch settlement inline and tags each discrepancy with its NIU', function () {
    $page = Livewire::test(ComplianceEngine::class)
        ->set('stage', 'settlement')
        ->set('nius', "M111111111111A\nM222222222222A\nM111111111111A")
        ->call('run')
        ->assertSet('batch.summary.total', 2)
        ->assertSee(__('app.ui.section_remediation'));

    // three lines, one duplicate → two settlement verifications
    expect(Verification::query()->where('stage', VerificationStage::Settlement->value)->count())->toBe(2);

    // Unseeded NIUs are rejected at settlement, so each must surface a discrepancy
    // carrying the offending NIU (the per-row attribution the batch view relies on).
    $discrepancies = $page->get('discrepancies');
    expect($discrepancies)->not->toBeEmpty()
        ->and(collect($discrepancies)->every(fn (array $d): bool => array_key_exists('niu', $d)))->toBeTrue();
});
