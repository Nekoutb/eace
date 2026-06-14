<?php

declare(strict_types=1);

use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Domain\Niu\Verdict;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verification;
use App\Services\Evidence\EvidenceArchiveService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'arch']);
    tenancy()->initialize($this->tenant);
    $this->actingAs(User::query()->create(['name' => 'A', 'email' => 'a', 'password' => bcrypt('x')]));
    Storage::fake('local');
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function archVerification(string $when): Verification
{
    Storage::disk('local')->put('invoices/inv.pdf', 'INVOICE-PDF-BYTES');

    $v = Verification::query()->create([
        'raw_input' => 'M058712345678X',
        'niu' => 'M058712345678X',
        'stage' => VerificationStage::Recording,
        'verdict' => Verdict::Conforme,
        'approval' => ApprovalDecision::Accepted,
        'exposure_xaf' => 0,
        'checks' => [['key' => 'niu_existence', 'passed' => true]],
        'invoice_checks' => [['key' => 'vat_calc', 'passed' => true]],
        'invoice_file_path' => 'invoices/inv.pdf',
    ]);

    // Backdate without touching Eloquent timestamps.
    Verification::query()->whereKey($v->getKey())->update(['created_at' => $when]);

    return $v->fresh();
}

it('archives a verification: purges the PDF but keeps a manifest with its hash', function () {
    $expectedHash = hash('sha256', 'INVOICE-PDF-BYTES');
    $verification = archVerification(now()->subMonths(4)->toDateTimeString());

    app(EvidenceArchiveService::class)->archive($verification);
    $verification->refresh();

    expect($verification->isArchived())->toBeTrue()
        ->and($verification->manifest)->not->toBeNull()
        ->and($verification->manifest['niu'])->toBe('M058712345678X')
        ->and($verification->manifest['evidence']['invoice_file_sha256'])->toBe($expectedHash)
        ->and($verification->invoice_data)->toBeNull()
        ->and(Storage::disk('local')->exists('invoices/inv.pdf'))->toBeFalse(); // freed
});

it('lists only verifications older than the retention window', function () {
    $old = archVerification(now()->subMonths(4)->toDateTimeString());
    $recent = archVerification(now()->subDays(10)->toDateTimeString());

    $eligible = app(EvidenceArchiveService::class)->eligibleForArchival(3)->pluck('id');

    expect($eligible)->toContain($old->getKey())
        ->and($eligible)->not->toContain($recent->getKey());
});

it('builds a downloadable ZIP export containing the evidence and manifest', function () {
    $verification = archVerification(now()->subDays(1)->toDateTimeString());

    $path = app(EvidenceArchiveService::class)->exportZip($verification);

    expect(is_file($path))->toBeTrue();

    $zip = new ZipArchive;
    $zip->open($path);
    expect($zip->locateName('evidence.json'))->not->toBeFalse()
        ->and($zip->locateName('invoice.pdf'))->not->toBeFalse();
    $zip->close();

    @unlink($path);
});
