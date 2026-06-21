<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\App\Resources\TaxpayerFiles\Pages\ListTaxpayerFiles;
use App\Jobs\RefreshTaxpayerFile;
use App\Models\TaxpayerFileExtraction;
use App\Models\TaxpayerFileRecord;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Registry\DgiExportDownloader;
use App\Services\Registry\TaxpayerFileIngestionService;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Build a FICHIER-shaped xlsx holding the given NIUs (one data row each). */
function taxpayerFileWith(array $nius): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fichier-'.bin2hex(random_bytes(6)).'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['N°', 'RAISON_SOCIALE', 'SIGLE', 'NIU', 'ACTIVITE_PRINCIPALE', 'REGIME', 'CRI', 'CENTRE_DE_RATTACHEMENT']));
    foreach (array_values($nius) as $i => $niu) {
        $writer->addRow(Row::fromValues([$i + 1, 'CO '.$i, '', $niu, 'COMMERCE', 'REEL', 'CRI', 'CIME']));
    }
    $writer->close();

    return $path;
}

it('rejects a collapsed snapshot and leaves the existing register untouched', function () {
    $ingestion = app(TaxpayerFileIngestionService::class);

    // A healthy register of four taxpayers.
    $first = taxpayerFileWith(['P100000000001A', 'P100000000002B', 'P100000000003C', 'P100000000004D']);
    $good = $ingestion->ingest($first, 'JAN 2026');
    @unlink($first);
    expect($good->status)->toBe('completed')->and($good->row_count)->toBe(4);

    // A truncated download with a single taxpayer — below the 50% safety floor.
    $collapsed = taxpayerFileWith(['P100000000001A']);
    expect(fn () => $ingestion->ingest($collapsed, 'FEB 2026'))
        ->toThrow(RuntimeException::class);
    @unlink($collapsed);

    // The bad run is recorded as failed, and NONE of its rows or the prune stuck:
    // the four original records still point at the first (good) extraction.
    $failed = TaxpayerFileExtraction::query()->where('period_label', 'FEB 2026')->first();
    expect($failed?->status)->toBe('failed');
    expect(TaxpayerFileRecord::query()->count())->toBe(4)
        ->and(TaxpayerFileRecord::query()->where('taxpayer_file_extraction_id', '!=', $good->id)->count())->toBe(0);
});

it('still accepts a snapshot that grows or holds steady', function () {
    $ingestion = app(TaxpayerFileIngestionService::class);

    $first = taxpayerFileWith(['P100000000001A', 'P100000000002B']);
    $ingestion->ingest($first, 'JAN 2026');
    @unlink($first);

    $second = taxpayerFileWith(['P100000000001A', 'P100000000002B', 'P100000000005E']);
    $extraction = $ingestion->ingest($second, 'FEB 2026');
    @unlink($second);

    expect($extraction->status)->toBe('completed')
        ->and(TaxpayerFileRecord::query()->count())->toBe(3);
});

it('skips the fetch command while another refresh holds the lock', function () {
    Cache::lock('taxpayer-file:fetch', 1800)->get();

    $path = taxpayerFileWith(['P100000000009Z']);

    $this->artisan('taxpayer-file:fetch', ['--file' => $path, '--period' => 'LOCKED 2026'])
        ->assertExitCode(0);

    @unlink($path);

    // The lock was held, so nothing was ingested under that period label.
    expect(TaxpayerFileExtraction::query()->where('period_label', 'LOCKED 2026')->exists())->toBeFalse();
});

it('archives each live download into the central tax-register store', function () {
    Storage::fake('local');
    config(['niu.wasabi.enabled' => false]);

    $path = taxpayerFileWith(['P100000000001A', 'P100000000002B']);

    // DgiExportDownloader is final; bind a stand-in that returns the local export.
    app()->instance(DgiExportDownloader::class, new class($path)
    {
        public function __construct(private string $path) {}

        /** @return array{0: ?string, 1: ?string} */
        public function download(): array
        {
            return [$this->path, null];
        }
    });

    $this->artisan('taxpayer-file:fetch')->assertExitCode(0);
    @unlink($path);

    $archived = Storage::disk('local')->allFiles('tax-register');

    expect($archived)->toHaveCount(1)
        ->and($archived[0])->toMatch('#^tax-register/\d{4}/\d{2}/\d{4}-\d{2}-\d{2}_\d{6}/.+\.xlsx$#');
});

it('queues a central, unique refresh job', function () {
    $job = new RefreshTaxpayerFile;

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('taxpayer-file:refresh');
});

it('lets an admin trigger the on-demand register refresh', function () {
    $tenant = Tenant::create(['id' => 'txf']);
    tenancy()->initialize($tenant);

    $admin = User::query()->create([
        'name' => 'Boss', 'email' => 'boss@txf.test',
        'password' => Hash::make('secret123'), 'role' => UserRole::Admin,
    ]);
    test()->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    Queue::fake();

    Livewire::test(ListTaxpayerFiles::class)
        ->callAction('updateRegister');

    Queue::assertPushed(RefreshTaxpayerFile::class);

    tenancy()->end();
    $tenant->delete();
});

it('hides the refresh action from non-admins', function () {
    $tenant = Tenant::create(['id' => 'txf2']);
    tenancy()->initialize($tenant);

    $recorder = User::query()->create([
        'name' => 'Clerk', 'email' => 'clerk@txf2.test',
        'password' => Hash::make('secret123'), 'role' => UserRole::Recorder,
    ]);
    test()->actingAs($recorder);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    Livewire::test(ListTaxpayerFiles::class)
        ->assertActionHidden('updateRegister');

    tenancy()->end();
    $tenant->delete();
});
