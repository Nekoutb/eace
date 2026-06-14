<?php

declare(strict_types=1);

use App\Domain\Niu\Verdict;
use App\Filament\App\Resources\Verifications\Pages\ListVerifications;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verification;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'ui']);
    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel('app');
    $this->user = User::query()->create([
        'name' => 'Agent Conformité',
        'email' => 'agent@demo.cm',
        'password' => bcrypt('password'),
    ]);
    $this->actingAs($this->user);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('renders the verifications list page', function () {
    Livewire::test(ListVerifications::class)->assertOk();
});

it('runs a verification through the recording action and persists the verdict', function () {
    Livewire::test(ListVerifications::class)
        ->callAction('record', data: ['raw_input' => 'M222222222222A']);

    expect(Verification::query()->where('niu', 'M222222222222A')->first()?->verdict)
        ->toBe(Verdict::Radie);
});
