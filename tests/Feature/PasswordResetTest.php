<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each->delete();
    foreach (['rp', 'rp2'] as $id) {
        $file = database_path('tenant'.$id.'.sqlite');
        if (is_file($file)) {
            @unlink($file);
        }
    }
});

it('issues a reset token in the client workspace, not the central database', function () {
    Notification::fake();
    $tenant = Tenant::create(['id' => 'rp']);
    $tenant->run(fn () => User::query()->create([
        'name' => 'Client', 'email' => 'client@rp.test', 'password' => Hash::make('old-secret-1'), 'role' => UserRole::Admin,
    ]));

    Filament::setCurrentPanel(Filament::getPanel('app'));

    Livewire::test(RequestPasswordReset::class)
        ->set('data.email', 'client@rp.test')
        ->call('request');

    // The token lands in the CLIENT's own workspace, never the central database.
    $inTenant = $tenant->run(fn () => DB::table('password_reset_tokens')->where('email', 'client@rp.test')->exists());
    $inCentral = DB::connection(config('tenancy.database.central_connection'))
        ->table('password_reset_tokens')->where('email', 'client@rp.test')->exists();

    expect($inTenant)->toBeTrue()
        ->and($inCentral)->toBeFalse();
});

it('resets a client password against their own workspace database', function () {
    $tenant = Tenant::create(['id' => 'rp2']);
    $tenant->run(fn () => User::query()->create([
        'name' => 'Client', 'email' => 'client@rp2.test', 'password' => Hash::make('old-secret-1'), 'role' => UserRole::Admin,
    ]));

    // A real token, created in the client's workspace (as the request step does).
    $token = $tenant->run(fn () => Password::broker()->createToken(User::query()->sole()));

    Filament::setCurrentPanel(Filament::getPanel('app'));

    Livewire::test(ResetPassword::class, ['email' => 'client@rp2.test', 'token' => $token])
        ->set('password', 'brand-new-pass-9')
        ->set('passwordConfirmation', 'brand-new-pass-9')
        ->call('resetPassword');

    $tenant->run(function () {
        $user = User::query()->sole();
        expect(Hash::check('brand-new-pass-9', $user->password))->toBeTrue()
            ->and(Hash::check('old-secret-1', $user->password))->toBeFalse();
    });
});

it('keeps a landlord password reset on the central database', function () {
    Notification::fake();
    // A central (landlord) user — no role column.
    User::query()->create(['name' => 'Boss', 'email' => 'boss@central.test', 'password' => Hash::make('old')]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(RequestPasswordReset::class)
        ->set('data.email', 'boss@central.test')
        ->call('request');

    expect(tenancy()->initialized)->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', 'boss@central.test')->exists())->toBeTrue();
});
