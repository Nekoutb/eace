<?php

declare(strict_types=1);

use App\Domain\Acf\AcfExtract;
use App\Domain\Acf\UnreadableAcfException;
use App\Domain\Niu\NiuNumber;
use App\Models\Tenant;
use App\Services\Acf\AcfIngestionService;
use App\Services\Acf\AcfOcr;
use App\Services\Acf\ClaudeAcfOcr;
use App\Services\Acf\FakeAcfOcr;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/*
 * Scanned-ACF OCR: a digital PDF is read by the free local text parser; a photo,
 * scan or image-only PDF falls back to AI vision OCR producing the SAME extract,
 * validated and persisted identically. Without a key (FakeAcfOcr) a scan reads
 * as "unreadable" — the pre-OCR behaviour.
 */

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** A recording OCR fake returning a fixed usable extract. */
function recordingOcr(?AcfExtract $extract = null): AcfOcr
{
    return new class($extract) implements AcfOcr
    {
        public int $calls = 0;

        public ?string $mediaType = null;

        public function __construct(private ?AcfExtract $extract) {}

        public function read(string $bytes, string $mediaType): AcfExtract
        {
            $this->calls++;
            $this->mediaType = $mediaType;

            return $this->extract ?? new AcfExtract(
                reference: '620264000123',
                niu: NiuNumber::tryFrom('M058712345678X'),
                taxpayerName: 'SCAN DEMO SARL',
                issuedAt: CarbonImmutable::now()->subDays(10)->startOfDay(),
                validityMonths: 3,
                hasMoratorium: false,
                regime: 'REEL',
                centre: 'CIME DOUALA',
                authenticityUrl: null,
            );
        }
    };
}

it('falls back to vision OCR for a scanned (image-only) PDF and records the certificate', function () {
    tenancy()->initialize(Tenant::create(['id' => 'ocr-a']));
    Storage::fake('local');

    // A PDF with no text layer — the local parser finds nothing.
    Storage::disk('local')->put('acf/scan.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3 scanned-image-only");

    $ocr = recordingOcr();
    app()->instance(AcfOcr::class, $ocr);

    $certificate = app(AcfIngestionService::class)->ingestFromUpload('acf/scan.pdf');

    expect($ocr->calls)->toBe(1)
        ->and($ocr->mediaType)->toBe('application/pdf')
        ->and($certificate->reference)->toBe('620264000123')
        ->and($certificate->niu)->toBe('M058712345678X')
        ->and($certificate->taxpayer_name)->toBe('SCAN DEMO SARL');
});

it('reads a photo (jpg) of an ACF directly with OCR', function () {
    tenancy()->initialize(Tenant::create(['id' => 'ocr-b']));
    Storage::fake('local');
    Storage::disk('local')->put('acf/photo.jpg', 'jpeg-bytes');

    $ocr = recordingOcr();
    app()->instance(AcfOcr::class, $ocr);

    $certificate = app(AcfIngestionService::class)->ingestFromUpload('acf/photo.jpg');

    expect($ocr->calls)->toBe(1)
        ->and($ocr->mediaType)->toBe('image/jpeg')
        ->and($certificate->reference)->toBe('620264000123');
});

it('still reports an unreadable document when OCR cannot extract anything', function () {
    tenancy()->initialize(Tenant::create(['id' => 'ocr-c']));
    Storage::fake('local');
    Storage::disk('local')->put('acf/blur.jpg', 'unreadable-bytes');

    app()->instance(AcfOcr::class, new FakeAcfOcr); // no-key behaviour: unusable extract

    app(AcfIngestionService::class)->ingestFromUpload('acf/blur.jpg');
})->throws(UnreadableAcfException::class);

it('never calls OCR when the digital text layer is readable', function () {
    tenancy()->initialize(Tenant::create(['id' => 'ocr-d']));

    $ocr = recordingOcr();
    app()->instance(AcfOcr::class, $ocr);

    // The text path (ingestFromText) is what a readable digital PDF feeds into.
    $certificate = app(AcfIngestionService::class)->ingestFromText(
        "Réference ACF : 620264999888\nLe contribuable : DIGITALE SARL de NIU M058712345678X\nvalable pour une durée de 3 mois à compter du 01/06/2026"
    );

    expect($ocr->calls)->toBe(0)
        ->and($certificate->reference)->toBe('620264999888');
});

it('maps the OCR tool payload into a typed extract (pure mapper)', function () {
    $mapper = new ClaudeAcfOcr('unused-key', 'unused-model');

    $extract = $mapper->mapResult([
        'reference' => '620264135721',
        'niu' => 'P099614234972X',
        'taxpayer_name' => 'ETS PHOTO SCAN',
        'issued_at' => '2026-06-15',
        'validity_months' => 3,
        'has_moratorium' => false,
        'regime' => 'IGS',
        'centre' => 'CDI YAOUNDE 5',
    ]);

    expect($extract->isUsable())->toBeTrue()
        ->and($extract->reference)->toBe('620264135721')
        ->and($extract->niu?->value)->toBe('P099614234972X')
        ->and($extract->issuedAt?->toDateString())->toBe('2026-06-15')
        ->and($extract->validityMonths)->toBe(3);

    // Garbage in → unusable out, never an exception.
    $bad = $mapper->mapResult(['reference' => 'ABC-NOT-DIGITS', 'issued_at' => 'yesterday']);
    expect($bad->isUsable())->toBeFalse();

    $empty = $mapper->mapResult([]);
    expect($empty->isUsable())->toBeFalse();
});
