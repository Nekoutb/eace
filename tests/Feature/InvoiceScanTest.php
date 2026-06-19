<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\App\Pages\ComplianceEngine;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'scan']);
    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel('app');
    $this->actingAs(User::query()->create([
        'name' => 'Agent',
        'email' => 'agent',
        'password' => bcrypt('secret'),
        'role' => UserRole::Admin,
    ]));
    Storage::fake('local');
    Storage::fake('livewire-tmp');
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('scans an uploaded invoice and auto-populates the control fields', function () {
    // Regression: a tenant-context upload + validation used to throw
    // League\Flysystem\UnableToRetrieveMetadata (file_size). With the central
    // livewire-tmp disk it reads cleanly and the OCR fills the fields.
    Livewire::test(ComplianceEngine::class)
        ->set('invoiceUpload', UploadedFile::fake()->create('invoice.pdf', 40, 'application/pdf'))
        ->assertHasNoErrors()
        ->assertSet('rawInput', 'M058712345678X')               // FakeInvoiceReader supplier NIU
        ->assertSet('extracted.supplier_niu', 'M058712345678X')
        ->call('run')
        ->assertSet('result.niu', 'M058712345678X');
});
