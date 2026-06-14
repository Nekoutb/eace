<?php

declare(strict_types=1);

use App\Domain\Niu\FiscalisStatus;
use App\Domain\Niu\NiuNumber;
use App\Models\TaxpayerFileExtraction;
use App\Models\TaxpayerFileRecord;
use App\Services\Registry\TaxpayerFileIngestionService;
use App\Services\Registry\TaxpayerFileResolver;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Build a tiny FICHIER-shaped xlsx for ingestion tests. */
function tinyTaxpayerFile(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fichier-'.bin2hex(random_bytes(6)).'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['N°', 'RAISON_SOCIALE', 'SIGLE', 'NIU', 'ACTIVITE_PRINCIPALE', 'REGIME', 'CRI', 'CENTRE_DE_RATTACHEMENT']));
    $writer->addRow(Row::fromValues([1, 'NEMECHI', 'ANTOINETTE', 'P107000258207S', 'COMMERCE', 'IGS PP', 'CRI EST', 'CDI HAUT-NYONG']));
    $writer->addRow(Row::fromValues([2, 'ALPHA SARL', '', 'M058712345678X', 'SERVICES', 'REEL', 'CRI CENTRE', 'CIME YAOUNDE']));
    $writer->close();

    return $path;
}

it('ingests an xlsx into taxpayer file records and records the extraction', function () {
    $path = tinyTaxpayerFile();

    $extraction = app(TaxpayerFileIngestionService::class)->ingest($path, 'TEST 2026', ['file_hash' => 'abc']);

    expect($extraction->status)->toBe('completed')
        ->and($extraction->row_count)->toBe(2)
        ->and(TaxpayerFileRecord::query()->count())->toBe(2)
        ->and(TaxpayerFileRecord::query()->where('niu', 'P107000258207S')->value('raison_sociale'))->toBe('NEMECHI');

    @unlink($path);
});

it('prunes NIUs that dropped off a newer extraction', function () {
    $first = tinyTaxpayerFile();
    app(TaxpayerFileIngestionService::class)->ingest($first, 'JAN 2026');
    @unlink($first);

    // A second file with only one of the two NIUs.
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fichier2-'.bin2hex(random_bytes(6)).'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['N°', 'RAISON_SOCIALE', 'SIGLE', 'NIU', 'ACTIVITE_PRINCIPALE', 'REGIME', 'CRI', 'CENTRE_DE_RATTACHEMENT']));
    $writer->addRow(Row::fromValues([1, 'NEMECHI', 'ANTOINETTE', 'P107000258207S', 'COMMERCE', 'IGS PP', 'CRI EST', 'CDI HAUT-NYONG']));
    $writer->close();

    app(TaxpayerFileIngestionService::class)->ingest($path, 'FEB 2026');
    @unlink($path);

    expect(TaxpayerFileRecord::query()->count())->toBe(1)
        ->and(TaxpayerFileRecord::query()->where('niu', 'M058712345678X')->exists())->toBeFalse();
});

it('resolves a NIU present in the file as active and on the active file', function () {
    $extraction = TaxpayerFileExtraction::query()->create([
        'period_label' => 'MARS 2026', 'status' => 'completed', 'row_count' => 1, 'extracted_at' => now(),
    ]);
    TaxpayerFileRecord::query()->create([
        'niu' => 'P107000258207S', 'raison_sociale' => 'NEMECHI', 'sigle' => 'ANTOINETTE',
        'regime' => 'IGS PP', 'centre' => 'CDI HAUT-NYONG', 'taxpayer_file_extraction_id' => $extraction->id,
    ]);

    $record = (new TaxpayerFileResolver)->resolve(NiuNumber::fromString('P107000258207S'));

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe(FiscalisStatus::Actif)
        ->and($record->onActiveFile)->toBeTrue()
        ->and($record->taxpayerName)->toBe('NEMECHI')
        ->and($record->source)->toBe('MARS 2026');
});

it('resolves a NIU absent from the file to null (introuvable)', function () {
    TaxpayerFileExtraction::query()->create(['period_label' => 'MARS 2026', 'status' => 'completed', 'extracted_at' => now()]);

    expect((new TaxpayerFileResolver)->resolve(NiuNumber::fromString('P999999999999Z')))->toBeNull();
});

it('ingests a local file through the fetch command', function () {
    $path = tinyTaxpayerFile();

    $this->artisan('taxpayer-file:fetch', ['--file' => $path, '--period' => 'CMD 2026'])
        ->assertExitCode(0);

    expect(TaxpayerFileExtraction::query()->where('period_label', 'CMD 2026')->where('status', 'completed')->exists())->toBeTrue();

    @unlink($path);
});
