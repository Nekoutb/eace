<?php

declare(strict_types=1);

use App\Mail\TaxpayerFetchReportMail;
use App\Services\Registry\TaxpayerFileIntegrityChecker;
use App\Services\Registry\TaxpayerIntegrityReport;
use Illuminate\Support\Facades\Mail;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function integrityTinyFile(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'integ-'.bin2hex(random_bytes(6)).'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['N°', 'RAISON_SOCIALE', 'SIGLE', 'NIU', 'ACTIVITE_PRINCIPALE', 'REGIME', 'CRI', 'CENTRE_DE_RATTACHEMENT']));
    $writer->addRow(Row::fromValues([1, 'NEMECHI', 'A', 'P107000258207S', 'COMMERCE', 'IGS', 'CRI EST', 'CDI']));
    $writer->addRow(Row::fromValues([2, 'ALPHA SARL', '', 'M058712345678X', 'SERVICES', 'REEL', 'CRI CENTRE', 'CIME']));
    $writer->close();

    return $path;
}

it('computes the integrity-report metrics', function () {
    $r = new TaxpayerIntegrityReport(
        status: 'completed', period: 'Export', totalRows: 100, rowsWithNiu: 98, distinctNiu: 95,
        rowsWithName: 100, niuFormatValid: 90, columnsFound: ['NIU'], columnsMissing: ['CRI'], previousRowCount: 96,
    );

    expect($r->duplicateNiu())->toBe(3)        // 98 with NIU − 95 distinct
        ->and($r->rowsMissingNiu())->toBe(2)   // 100 − 98
        ->and($r->delta())->toBe(-1)           // 95 − 96
        ->and($r->niuCompleteness())->toBe(98.0)
        ->and($r->ok())->toBeFalse()           // a column is missing
        ->and($r->warnings())->not->toBeEmpty();
});

it('checks the file schema, completeness and validity', function () {
    $path = integrityTinyFile();

    $m = app(TaxpayerFileIntegrityChecker::class)->check($path);

    expect($m['total'])->toBe(2)
        ->and($m['rowsWithNiu'])->toBe(2)
        ->and($m['niuValid'])->toBe(2)         // both NIUs match the canonical format
        ->and($m['rowsWithName'])->toBe(2)
        ->and($m['columnsMissing'])->toBe([])  // all expected columns present
        ->and($m['fileHash'])->not->toBeNull();

    @unlink($path);
});

it('renders the report email', function () {
    $r = new TaxpayerIntegrityReport(
        status: 'completed', period: 'Export 22/06/2026', fileHash: 'abc123', fileSizeBytes: 1048576,
        totalRows: 2, rowsWithNiu: 2, distinctNiu: 2, rowsWithName: 2, niuFormatValid: 2,
        columnsFound: ['NIU'], columnsMissing: [], previousRowCount: 1,
    );

    $html = (new TaxpayerFetchReportMail($r))->render();

    expect($html)->toContain('Data-integrity checks')->toContain('Export 22/06/2026');
});

it('emails a confirmation + integrity report after a fetch', function () {
    config(['niu.taxpayer_file.report_email' => 'ops@cm-ea.com']);
    Mail::fake();
    $path = integrityTinyFile();

    $this->artisan('taxpayer-file:fetch', ['--file' => $path, '--period' => 'Export 22/06/2026'])->assertSuccessful();

    Mail::assertSent(TaxpayerFetchReportMail::class, fn (TaxpayerFetchReportMail $m): bool => $m->report->status === 'completed' && $m->report->distinctNiu === 2);

    @unlink($path);
});
