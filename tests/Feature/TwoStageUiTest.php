<?php

declare(strict_types=1);

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
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

it('records an accepted verification through the recording action', function () {
    uiAuthenticAcf();

    Livewire::test(ComplianceEngine::class)
        ->callAction('record', ['raw_input' => 'M058712345678X']);

    $verification = Verification::query()->where('stage', VerificationStage::Recording->value)->first();

    expect($verification)->not->toBeNull()
        ->and($verification->approval)->toBe(ApprovalDecision::Accepted);
});

it('rejects a recording with neither an ACF nor a NIU', function () {
    Livewire::test(ComplianceEngine::class)
        ->callAction('record', []);

    expect(Verification::query()->count())->toBe(0);
});

it('runs a batch settlement through the settle action', function () {
    Livewire::test(ComplianceEngine::class)
        ->callAction('settle', ['nius' => "M111111111111A\nM222222222222A\nM111111111111A"]);

    // three lines, one duplicate → two settlement verifications
    expect(Verification::query()->where('stage', VerificationStage::Settlement->value)->count())->toBe(2);
});
