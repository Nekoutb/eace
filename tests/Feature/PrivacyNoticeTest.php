<?php

declare(strict_types=1);

use App\Models\PendingSignup;
use Illuminate\Support\Facades\Mail;

it('serves the public privacy notice in both languages', function () {
    session(['locale' => 'en']);
    $this->get('/privacy')->assertOk()->assertSee('Privacy notice')->assertSee('Your rights');

    session(['locale' => 'fr']);
    $this->get('/privacy')->assertOk()->assertSee('Politique de confidentialité')->assertSee('Vos droits');
});

it('requires privacy consent to sign up', function () {
    config(['niu.self_service_signup' => true]);
    Mail::fake();

    $this->post('/register', ['org_name' => 'No Consent', 'name' => 'X', 'email' => 'noc@x.test'])
        ->assertSessionHasErrors('consent');

    expect(PendingSignup::query()->where('email', 'noc@x.test')->exists())->toBeFalse();
});

it('records the privacy-notice consent (version + timestamp) on a sign-up', function () {
    config(['niu.self_service_signup' => true, 'niu.data_protection.policy_version' => '1.0']);
    Mail::fake();

    $this->post('/register', ['org_name' => 'With Consent', 'name' => 'Y', 'email' => 'yes@x.test', 'consent' => '1'])
        ->assertRedirect(route('signup.sent'));

    $pending = PendingSignup::query()->where('email', 'yes@x.test')->first();
    expect($pending)->not->toBeNull()
        ->and($pending->consent_version)->toBe('1.0')
        ->and($pending->consent_accepted_at)->not->toBeNull();
});
