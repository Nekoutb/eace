<?php

declare(strict_types=1);

use App\Domain\Niu\Verdict;
use App\Filament\App\Pages\ComplianceEngine;
use App\Filament\App\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\App\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Filament\App\Resources\TaxpayerFiles\Pages\ListTaxpayerFiles;
use App\Filament\App\Resources\Verifications\Pages\ListVerifications;
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

it('runs a verification inline and reveals the discrepancies on the same screen', function () {
    $page = Livewire::test(ComplianceEngine::class)
        ->set('rawInput', 'M222222222222A')
        ->call('run')
        ->assertSet('result.verdict.label', Verdict::Radie->label())
        ->assertSee(__('app.ui.results'))
        ->assertSee(__('app.ui.section_remediation'));

    // A struck-off NIU fails checks, so the comments section must list them inline.
    expect($page->get('discrepancies'))->not->toBeEmpty()
        ->and(Verification::query()->where('niu', 'M222222222222A')->first()?->verdict)
        ->toBe(Verdict::Radie);
});
