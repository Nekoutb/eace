<?php

declare(strict_types=1);

use App\Models\PendingSignup;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each->delete();
    $file = database_path('tenantwith-consent.sqlite');
    if (is_file($file)) {
        @unlink($file);
    }
});

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

    expect(PendingSignup::query()->where('email', 'noc@x.test')->exists())->toBeFalse()
        ->and(Tenant::find('no-consent'))->toBeNull(); // nothing provisioned without consent
});

it('records the privacy-notice consent (version + timestamp) on a sign-up', function () {
    config(['niu.self_service_signup' => true, 'niu.data_protection.policy_version' => '1.0']);
    Mail::fake();

    $this->post('/register', ['org_name' => 'With Consent', 'name' => 'Y', 'email' => 'yes@x.test', 'consent' => '1'])
        ->assertRedirect(route('signup.done'));

    // Instant onboarding stamps the accepted notice on the workspace itself.
    $tenant = Tenant::find('with-consent');
    expect($tenant)->not->toBeNull()
        ->and($tenant->consent_version)->toBe('1.0')
        ->and($tenant->consent_accepted_at)->not->toBeNull();
});
