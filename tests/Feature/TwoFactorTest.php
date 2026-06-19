<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\Auth\AppAuthentication;
use App\Models\Tenant;
use App\Models\User;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'mfa']);
    tenancy()->initialize($this->tenant);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function mfaUser(): User
{
    return User::query()->create([
        'name' => 'Agent',
        'email' => 'agent@mfa.test',
        'password' => bcrypt('secret123'),
        'role' => UserRole::Admin,
    ]);
}

it('implements the Filament app-authentication contracts', function () {
    expect(mfaUser())
        ->toBeInstanceOf(HasAppAuthentication::class)
        ->toBeInstanceOf(HasAppAuthenticationRecovery::class);
});

it('stores the TOTP secret encrypted at rest and reads it back', function () {
    $user = mfaUser();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    expect($user->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP');

    // The raw column value must NOT be the plaintext secret.
    $raw = $user->getConnection()->table('users')->where('id', $user->getKey())->value('app_authentication_secret');
    expect($raw)->not->toBeNull()->and($raw)->not->toBe('JBSWY3DPEHPK3PXP');
});

it('stores recovery codes and exposes the holder name', function () {
    $user = mfaUser();
    $user->saveAppAuthenticationRecoveryCodes(['code-one', 'code-two']);

    expect($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe(['code-one', 'code-two'])
        ->and($user->getAppAuthenticationHolderName())->toBe('agent@mfa.test');
});

it('requires multi-factor authentication on both panels', function () {
    foreach (['app', 'admin'] as $panelId) {
        $panel = Filament::getPanel($panelId);
        expect($panel->getMultiFactorAuthenticationProviders())->not->toBeEmpty()
            ->and($panel->isMultiFactorAuthenticationRequired())->toBeTrue();
    }
});

it('renders the enrolment QR as a single valid SVG data URI (not double-wrapped)', function () {
    test()->actingAs(mfaUser());
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $uri = app(AppAuthentication::class)->generateQrCodeDataUri('FLW3DAQOXC2OQH43');

    expect($uri)->toStartWith('data:image/svg+xml;base64,');

    // The decoded payload must be the SVG itself, NOT another data: URI (the
    // double-encoding bug that left the QR code blank in the set-up dialog).
    $decoded = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true);
    expect($decoded)->toBeString()
        ->and($decoded)->not->toStartWith('data:')
        ->and($decoded)->toContain('<svg');
});
