<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\Auth\Login;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

/*
 * REGRESSION — the "Marie Michelle Foe" 2FA lockout. Filament's MFA challenge
 * re-fetches the challenged user BY ID from the auth provider while rendering the
 * challenge form. The old login tore the workspace context down as soon as
 * parent::authenticate() returned null ("challenge pending"), so that lookup ran
 * against the CENTRAL users table: an id with no central counterpart resolved to
 * null (TypeError 500 on the 2FA screen — the "2FA not working" reports), and an
 * id colliding with a central row would have challenged the WRONG user. The login
 * must keep + pin the workspace for the whole challenge conversation. In these
 * tests the central users table is EMPTY, so any central lookup is guaranteed to
 * blow up — exactly the reported scenario.
 */

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** A workspace with one 2FA-enrolled user; returns [tenant, secret]. */
function mfaWorkspace(string $id, string $email, ?string $secret = null): array
{
    $tenant = Tenant::create(['id' => $id, 'name' => strtoupper($id)]);
    tenancy()->initialize($tenant);
    $user = User::query()->create([
        'name' => 'Marie Michelle',
        'email' => $email,
        'password' => Hash::make('secret-password-1'),
        'role' => UserRole::Recorder,
    ]);
    if ($secret !== null) {
        $user->forceFill(['app_authentication_secret' => $secret])->save();
    }
    tenancy()->end();

    return [$tenant, $secret];
}

it('renders the 2FA challenge without crashing for a user whose id has no central counterpart', function () {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();
    [$tenant] = mfaWorkspace('mfa-a', 'marie@mfa.test', $secret);

    Filament::setCurrentPanel('app');

    $component = Livewire::test(Login::class)
        ->fillForm(['email' => 'marie@mfa.test', 'password' => 'secret-password-1'])
        ->call('authenticate')
        ->assertOk(); // the old code 500'd HERE (TypeError: isEnabled(null))

    // The challenge is pending and the workspace is pinned for the conversation.
    expect($component->instance()->userUndertakingMultiFactorAuthentication)->not->toBeNull()
        ->and(session('tenant_id'))->toBe('mfa-a')
        ->and(auth()->check())->toBeFalse(); // not signed in until the code passes
});

it('keeps the workspace through a WRONG code so the retry re-renders in-tenant', function () {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();
    mfaWorkspace('mfa-a', 'marie@mfa.test', $secret);

    Filament::setCurrentPanel('app');

    $component = Livewire::test(Login::class)
        ->fillForm(['email' => 'marie@mfa.test', 'password' => 'secret-password-1'])
        ->call('authenticate');

    $component->set('data.multiFactor.app.code', '000000')
        ->call('authenticate')
        ->assertHasErrors(); // wrong code — but no TypeError, no lost context

    expect(session('tenant_id'))->toBe('mfa-a')
        ->and(auth()->check())->toBeFalse();
});

it('signs the user in with a correct authenticator code', function () {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();
    mfaWorkspace('mfa-a', 'marie@mfa.test', $secret);

    Filament::setCurrentPanel('app');

    $component = Livewire::test(Login::class)
        ->fillForm(['email' => 'marie@mfa.test', 'password' => 'secret-password-1'])
        ->call('authenticate');

    $component->set('data.multiFactor.app.code', $google2fa->getCurrentOtp($secret))
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(auth()->check())->toBeTrue()
        ->and((string) auth()->user()->email)->toBe('marie@mfa.test')
        ->and(session('tenant_id'))->toBe('mfa-a');
});

it('reconciles a stale workspace pin when a different account signs in on the same browser', function () {
    mfaWorkspace('mfa-a', 'marie@mfa.test', (new Google2FA)->generateSecretKey());
    mfaWorkspace('mfa-b', 'ben@mfa.test'); // no 2FA — signs straight in

    Filament::setCurrentPanel('app');
    session()->put('tenant_id', 'mfa-a'); // abandoned mid-challenge pin from user A

    Livewire::test(Login::class)
        ->fillForm(['email' => 'ben@mfa.test', 'password' => 'secret-password-1'])
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(auth()->check())->toBeTrue()
        ->and((string) auth()->user()->email)->toBe('ben@mfa.test')
        ->and(session('tenant_id'))->toBe('mfa-b'); // repointed, not stuck on A
});
