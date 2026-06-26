<?php

declare(strict_types=1);

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Approval\VerificationStage;
use App\Domain\Auth\UserRole;
use App\Filament\App\Pages\ComplianceEngine;
use App\Models\AcfCertificate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verification;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * Multiple tax-clearance certificates can be queued ("Add another certificate")
 * and controlled together in one run — each certificate is controlled independently
 * and gets its OWN verification rows + audit lines, linked to its own ACF.
 */

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'multiacf']);
    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel('app');
    $this->actingAs(User::query()->create([
        'name' => 'Agent',
        'email' => 'agent',
        'password' => bcrypt('secret'),
        'role' => UserRole::Admin,
    ]));
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function multiAcfCert(string $reference, string $niu): AcfCertificate
{
    $acf = new AcfCertificate([
        'reference' => $reference,
        'niu' => $niu,
        'taxpayer_name' => 'SARL '.$reference,
        'issued_at' => now(),
        'validity_months' => 3,
        'authenticity_status' => AcfAuthenticityStatus::Authentic,
    ]);
    $acf->recomputeValidity()->save();

    return $acf;
}

it('queues several certificates and controls each one independently in one run', function () {
    $a = multiAcfCert('111000111000', 'M058712345678X');
    $b = multiAcfCert('222000222000', 'P112233445566Q');

    Livewire::test(ComplianceEngine::class)
        ->set('stage', 'recording')
        ->set('acfReference', $a->reference)
        ->set('rawInput', $a->niu)
        ->call('stageCurrentAcf')
        ->assertCount('stagedAcfs', 1)
        ->set('acfReference', $b->reference)
        ->set('rawInput', $b->niu)
        ->call('run')
        ->assertSet('stagedAcfs', []); // queue consumed by the run

    $verifications = Verification::query()->where('stage', VerificationStage::Recording->value)->get();
    expect($verifications)->toHaveCount(2);

    // Each certificate produced its own verification, linked to its own ACF.
    $byNiu = $verifications->keyBy('niu');
    expect($byNiu['M058712345678X']->acf_certificate_id)->toBe($a->id)
        ->and($byNiu['P112233445566Q']->acf_certificate_id)->toBe($b->id);
});

it('removes a queued certificate before running', function () {
    Livewire::test(ComplianceEngine::class)
        ->set('stage', 'recording')
        ->set('rawInput', 'M058712345678X')
        ->call('stageCurrentAcf')
        ->assertCount('stagedAcfs', 1)
        ->call('unstageAcf', 0)
        ->assertCount('stagedAcfs', 0);
});

it('refuses to queue an empty certificate', function () {
    Livewire::test(ComplianceEngine::class)
        ->set('stage', 'recording')
        ->call('stageCurrentAcf')
        ->assertCount('stagedAcfs', 0);

    expect(Verification::query()->count())->toBe(0);
});
