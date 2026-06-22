<?php

declare(strict_types=1);

use App\Mail\TaxpayerFetchReportMail;
use App\Services\Registry\TaxpayerChangeSet;
use App\Services\Registry\TaxpayerFileIngestionService;
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

/** @param  list<string>  $nius */
function niuFile(array $nius): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'niu-'.bin2hex(random_bytes(6)).'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['N°', 'RAISON_SOCIALE', 'SIGLE', 'NIU', 'ACTIVITE_PRINCIPALE', 'REGIME', 'CRI', 'CENTRE_DE_RATTACHEMENT']));
    $i = 1;
    foreach ($nius as $niu) {
        $writer->addRow(Row::fromValues([$i++, 'CO '.$niu, '', $niu, 'COMMERCE', 'IGS', 'CRI', 'CDI']));
    }
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

it('tracks added/removed NIUs and classifies first-time vs returning across runs', function () {
    $ingest = app(TaxpayerFileIngestionService::class);

    $a = 'P107000258207S';   // stays in every file
    $b = 'M058712345678X';   // drops after F1, returns in F3
    $c = 'A012345678901Z';   // first appears in F2

    // F1 — first ever load: both NIUs are first-time, nothing removed.
    $f1 = niuFile([$a, $b]);
    $ingest->ingest($f1, 'F1');
    $cs1 = $ingest->lastChangeSet();
    expect($cs1)->not->toBeNull()
        ->and($cs1->addedTotal)->toBe(2)
        ->and($cs1->addedFirstTime)->toBe(2)
        ->and($cs1->addedReturning)->toBe(0)
        ->and($cs1->removedTotal)->toBe(0);

    // F2 — B falls off, C is added (genuinely first-time).
    $f2 = niuFile([$a, $c]);
    $ingest->ingest($f2, 'F2');
    $cs2 = $ingest->lastChangeSet();
    expect($cs2->addedTotal)->toBe(1)
        ->and($cs2->addedFirstTime)->toBe(1)
        ->and($cs2->addedReturning)->toBe(0)
        ->and($cs2->added)->toBe([$c])
        ->and($cs2->removedTotal)->toBe(1)
        ->and($cs2->removed)->toBe([$b]);

    // F3 — B comes back: it must be recognised as RETURNING (seen in F1), not first-time.
    $f3 = niuFile([$a, $b]);
    $ingest->ingest($f3, 'F3');
    $cs3 = $ingest->lastChangeSet();
    expect($cs3->addedTotal)->toBe(1)
        ->and($cs3->addedReturning)->toBe(1)
        ->and($cs3->addedFirstTime)->toBe(0)
        ->and($cs3->added)->toBe([$b])
        ->and($cs3->returning)->toBe([$b])
        ->and($cs3->removedTotal)->toBe(1)
        ->and($cs3->removed)->toBe([$c]);

    @unlink($f1);
    @unlink($f2);
    @unlink($f3);
});

it('renders the change set and attaches the NIU change CSV', function () {
    $changes = new TaxpayerChangeSet(
        addedTotal: 3, addedFirstTime: 2, addedReturning: 1, removedTotal: 1,
        added: ['A012345678901Z', 'B012345678901Y', 'C012345678901X'],
        removed: ['M058712345678X'],
        returning: ['C012345678901X'],
    );
    $r = new TaxpayerIntegrityReport(
        status: 'completed', period: 'Export 22/06/2026', totalRows: 3, rowsWithNiu: 3,
        distinctNiu: 3, rowsWithName: 3, niuFormatValid: 3, columnsFound: ['NIU'], columnsMissing: [],
        previousRowCount: 1, changes: $changes,
    );

    $mailable = new TaxpayerFetchReportMail($r);
    $html = $mailable->render();

    expect($html)->toContain('Register changes vs previous file')
        ->toContain('first-time ever')
        ->and($mailable->attachments())->toHaveCount(1);
});

it('emails a confirmation + integrity report after a fetch', function () {
    config(['niu.taxpayer_file.report_email' => 'ops@cm-ea.com']);
    Mail::fake();
    $path = integrityTinyFile();

    $this->artisan('taxpayer-file:fetch', ['--file' => $path, '--period' => 'Export 22/06/2026'])->assertSuccessful();

    Mail::assertSent(TaxpayerFetchReportMail::class, fn (TaxpayerFetchReportMail $m): bool => $m->report->status === 'completed' && $m->report->distinctNiu === 2);

    @unlink($path);
});
