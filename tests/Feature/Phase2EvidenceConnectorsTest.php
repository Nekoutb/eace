<?php

declare(strict_types=1);

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfStatus;
use App\Domain\Approval\VerificationStage;
use App\Domain\Fiscalis\FiscalisPresence;
use App\Domain\Niu\Verdict;
use App\Models\AcfCertificate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verification;
use App\Services\Acf\AcfAuthenticationService;
use App\Services\Acf\AcfAuthenticityConnector;
use App\Services\Acf\AcfAuthenticityResult;
use App\Services\Acf\FakeAcfAuthenticityConnector;
use App\Services\Acf\Harmony2AcfConnector;
use App\Services\Fiscalis\DgiFiscalisConnector;
use App\Services\Fiscalis\FakeFiscalisConnector;
use App\Services\Fiscalis\FiscalisConnector;
use App\Services\Fiscalis\FiscalisResult;
use App\Services\Fiscalis\FiscalisStatusService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'ph2']);
    tenancy()->initialize($this->tenant);
    $this->actingAs(User::query()->create(['name' => 'A', 'email' => 'a', 'password' => bcrypt('x')]));
    Storage::fake('local');
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function ph2Acf(): AcfCertificate
{
    return AcfCertificate::query()->create([
        'reference' => 'ACF-TEST-1',
        'niu' => 'P099614234972X',
        'issued_at' => now()->subMonth(),
        'expires_at' => now()->addMonths(2),
        'validity_months' => 3,
        'status' => AcfStatus::Valid,
    ]);
}

function ph2Verification(): Verification
{
    return Verification::query()->create([
        'raw_input' => 'P099614234972X',
        'niu' => 'P099614234972X',
        'stage' => VerificationStage::Recording,
        'verdict' => Verdict::Conforme,
        'exposure_xaf' => 0,
    ]);
}

// --- ACF authenticity ------------------------------------------------------

it('stores the Harmony2 verdict screenshot and updates the certificate', function () {
    app()->instance(AcfAuthenticityConnector::class, new class implements AcfAuthenticityConnector
    {
        public function verify(string $reference): AcfAuthenticityResult
        {
            return new AcfAuthenticityResult(AcfAuthenticityStatus::Authentic, 'https://fanr.harmony2.cm/x', 'authentique', 'PNG-BYTES');
        }
    });

    $acf = ph2Acf();
    $result = app(AcfAuthenticationService::class)->authenticate($acf);
    $acf->refresh();

    expect($result->status)->toBe(AcfAuthenticityStatus::Authentic)
        ->and($acf->authenticity_status)->toBe(AcfAuthenticityStatus::Authentic)
        ->and($acf->authenticity_snapshot_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($acf->authenticity_snapshot_path))->toBeTrue()
        ->and($acf->last_verified_at)->not->toBeNull();
});

it('records ACF as unverified without fabricating a screenshot when the portal is unreachable', function () {
    app()->instance(AcfAuthenticityConnector::class, new FakeAcfAuthenticityConnector);

    $acf = ph2Acf();
    app(AcfAuthenticationService::class)->authenticate($acf);
    $acf->refresh();

    expect($acf->authenticity_status)->toBe(AcfAuthenticityStatus::Unverified)
        ->and($acf->authenticity_snapshot_path)->toBeNull()
        ->and($acf->last_verified_at)->not->toBeNull();
});

// --- Fiscalis presence -----------------------------------------------------

it('stores the DGI consultation screenshot and updates the verification', function () {
    app()->instance(FiscalisConnector::class, new class implements FiscalisConnector
    {
        public function check(string $niu): FiscalisResult
        {
            return new FiscalisResult(FiscalisPresence::Present, 'https://teledeclaration-dgi.cm/x', 1, '1 contribuable', 'PNG-BYTES');
        }
    });

    $verification = ph2Verification();
    $result = app(FiscalisStatusService::class)->capture($verification);
    $verification->refresh();

    expect($result->presence)->toBe(FiscalisPresence::Present)
        ->and($verification->fiscalis_presence)->toBe(FiscalisPresence::Present)
        ->and($verification->fiscalis_snapshot_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($verification->fiscalis_snapshot_path))->toBeTrue()
        ->and($verification->fiscalis_checked_at)->not->toBeNull();
});

it('records Fiscalis as unverified without a screenshot when the portal is unreachable', function () {
    app()->instance(FiscalisConnector::class, new FakeFiscalisConnector);

    $verification = ph2Verification();
    app(FiscalisStatusService::class)->capture($verification);
    $verification->refresh();

    expect($verification->fiscalis_presence)->toBe(FiscalisPresence::Unverified)
        ->and($verification->fiscalis_snapshot_path)->toBeNull()
        ->and($verification->fiscalis_checked_at)->not->toBeNull();
});

// --- Connector resolution --------------------------------------------------

it('resolves the fake connectors when the headless browser is disabled', function () {
    config(['services.browsershot.enabled' => false]);

    expect(app(AcfAuthenticityConnector::class))->toBeInstanceOf(FakeAcfAuthenticityConnector::class)
        ->and(app(FiscalisConnector::class))->toBeInstanceOf(FakeFiscalisConnector::class);
});

it('resolves the live connectors when the headless browser is enabled and Chromium is pinned', function () {
    config([
        'services.browsershot.enabled' => true,
        'services.browsershot.chrome_path' => 'C:/fake/chrome.exe',
    ]);

    expect(app(AcfAuthenticityConnector::class))->toBeInstanceOf(Harmony2AcfConnector::class)
        ->and(app(FiscalisConnector::class))->toBeInstanceOf(DgiFiscalisConnector::class);
});
