<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Domain\Niu\Verdict;
use App\Filament\App\Pages\AuditLogs;
use App\Filament\App\Pages\ComplianceEngine;
use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\App\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Filament\App\Resources\TaxpayerFiles\Pages\ListTaxpayerFiles;
use App\Filament\App\Resources\Verifications\Pages\ListVerifications;
use App\Filament\App\Widgets\ComplianceStats;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verification;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'ui']);
    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel('app');
    $this->user = User::query()->create([
        'name' => 'Agent Conformité',
        'email' => 'agent@demo.cm',
        'password' => bcrypt('password'),
        'role' => UserRole::Admin,
    ]);
    $this->actingAs($this->user);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('renders the verifications list page', function () {
    Livewire::test(ListVerifications::class)->assertOk();
});

it('renders the audit logs list and a record view with the evidence', function () {
    Livewire::test(ListAuditLogs::class)->assertOk();

    Livewire::test(ComplianceEngine::class)
        ->set('rawInput', 'M058712345678X')
        ->call('run');

    $verification = Verification::query()->latest('id')->first();

    Livewire::test(ViewAuditLog::class, ['record' => $verification?->getKey()])
        ->assertOk()
        ->assertSee(__('app.audit.section_evidence'));
});

it('renders the taxpayer-file download log', function () {
    Livewire::test(ListTaxpayerFiles::class)->assertOk();
});

it('renders the dashboard, its stats widget, and the audit-logs hub', function () {
    Livewire::test(Dashboard::class)->assertOk();
    Livewire::test(ComplianceStats::class)->assertOk();
    Livewire::test(AuditLogs::class)
        ->assertOk()
        ->assertSee(__('app.audit.hub_files_title'))
        ->assertSee(__('app.audit.hub_evidence_title'));
});

it('runs a verification inline and reveals the discrepancies on the same screen', function () {
    $page = Livewire::test(ComplianceEngine::class)
        ->set('rawInput', 'M222222222222A')
        ->call('run')
        // The displayed verdict is Non-compliant because a check failed — even
        // though the stored NIU-risk grade is still Radie (asserted below).
        ->assertSet('result.verdict.label', __('app.verdict.non_conforme'))
        ->assertSet('result.conclusion.payable', false)
        // Recording stage phrases the rejection as "must NOT be recorded".
        ->assertSet('result.conclusion.label', __('app.ui.conclusion_not_recordable'))
        ->assertSee(__('app.ui.results'))
        ->assertSee(__('app.ui.section_remediation'));

    // A struck-off NIU fails checks, so the comments section must list them inline.
    expect($page->get('discrepancies'))->not->toBeEmpty()
        ->and(Verification::query()->where('niu', 'M222222222222A')->first()?->verdict)
        ->toBe(Verdict::Radie);
});
