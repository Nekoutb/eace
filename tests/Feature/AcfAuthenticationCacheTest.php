<?php

declare(strict_types=1);

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfStatus;
use App\Models\AcfAuthentication;
use App\Models\AcfCertificate;
use App\Models\Tenant;
use App\Services\Acf\AcfAuthenticationService;
use App\Services\Acf\AcfAuthenticityConnector;
use App\Services\Acf\AcfAuthenticityResult;
use App\Services\Acf\FakeAcfAuthenticityConnector;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    AcfAuthentication::query()->delete();
    $this->tenant = Tenant::create(['id' => 'acfc']);
    tenancy()->initialize($this->tenant);
    Storage::fake('local');
    Storage::fake('central');
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
    AcfAuthentication::query()->delete();
});

function cacheAcf(string $reference): AcfCertificate
{
    return AcfCertificate::query()->create([
        'reference' => $reference,
        'niu' => 'M058712345678X',
        'issued_at' => now()->subMonth(),
        'expires_at' => now()->addMonths(2),
        'validity_months' => 3,
        'status' => AcfStatus::Valid,
    ]);
}

/** A connector that records whether it was actually called. */
function recordingConnector(AcfAuthenticityStatus $status, ?string $png = null): AcfAuthenticityConnector
{
    return new class($status, $png) implements AcfAuthenticityConnector
    {
        public bool $called = false;

        public function __construct(private AcfAuthenticityStatus $status, private ?string $png) {}

        public function verify(string $reference): AcfAuthenticityResult
        {
            $this->called = true;

            return new AcfAuthenticityResult($this->status, 'https://fanr.harmony2.cm/authentification?reference='.$reference, 'reconnu', $this->png);
        }
    };
}

it('caches a definitive verdict centrally on first authentication', function () {
    $conn = recordingConnector(AcfAuthenticityStatus::Authentic, 'PNG-BYTES');
    app()->instance(AcfAuthenticityConnector::class, $conn);

    $acf = cacheAcf('620000000111');
    $result = app(AcfAuthenticationService::class)->authenticate($acf);

    expect($conn->called)->toBeTrue()
        ->and($result->wasReused())->toBeFalse()
        ->and($acf->fresh()->authenticity_status)->toBe(AcfAuthenticityStatus::Authentic);

    $cached = AcfAuthentication::query()->where('reference', '620000000111')->first();
    expect($cached)->not->toBeNull()
        ->and($cached->authenticity_status)->toBe(AcfAuthenticityStatus::Authentic)
        ->and($cached->snapshot_path)->not->toBeNull()
        ->and(Storage::disk('central')->exists($cached->snapshot_path))->toBeTrue();
});

it('reuses a cached verdict for the same reference without calling the portal', function () {
    // Customer A already authenticated this 620 reference yesterday.
    AcfAuthentication::query()->create([
        'reference' => '620000000222',
        'niu' => 'M058712345678X',
        'authenticity_status' => AcfAuthenticityStatus::Authentic,
        'result_text' => 'reconnu',
        'authenticity_url' => 'https://fanr.harmony2.cm/authentification?reference=620000000222',
        'snapshot_path' => null,
        'checked_at' => now()->subDay(),
    ]);

    // Customer B's connector MUST NOT be called — the verdict is reused.
    $conn = recordingConnector(AcfAuthenticityStatus::Unverified);
    app()->instance(AcfAuthenticityConnector::class, $conn);

    $acf = cacheAcf('620000000222');
    $result = app(AcfAuthenticationService::class)->authenticate($acf);

    expect($conn->called)->toBeFalse()
        ->and($result->status)->toBe(AcfAuthenticityStatus::Authentic)
        ->and($result->wasReused())->toBeTrue()
        ->and($acf->fresh()->authenticity_status)->toBe(AcfAuthenticityStatus::Authentic)
        ->and($acf->fresh()->last_verified_at?->toDateString())->toBe(now()->subDay()->toDateString());
});

it('does not cache an unverified (portal outage) verdict, so it stays retryable', function () {
    app()->instance(AcfAuthenticityConnector::class, new FakeAcfAuthenticityConnector);

    $acf = cacheAcf('620000000333');
    $result = app(AcfAuthenticationService::class)->authenticate($acf);

    expect($result->status)->toBe(AcfAuthenticityStatus::Unverified)
        ->and(AcfAuthentication::query()->where('reference', '620000000333')->exists())->toBeFalse();
});
