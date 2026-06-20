<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Mail\NewWorkspaceAdminNotice;
use App\Mail\WorkspaceCredentialsMail;
use App\Mail\WorkspaceVerifyMail;
use App\Models\PendingSignup;
use App\Models\Tenant;
use App\Models\TenantLogin;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['niu.self_service_signup' => true]);
    Mail::fake();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each->delete();
    foreach (['acme-ltd', 'beta-co', 'gamma-sarl'] as $id) {
        $file = database_path('tenant'.$id.'.sqlite');
        if (is_file($file)) {
            @unlink($file);
        }
    }
});

it('holds a pending sign-up and emails a verification link without creating a workspace', function () {
    $this->post('/register', [
        'org_name' => 'Acme Ltd', 'name' => 'Jane', 'email' => 'jane@acme.test',
    ])->assertRedirect(route('signup.sent'));

    expect(PendingSignup::query()->where('email', 'jane@acme.test')->exists())->toBeTrue()
        ->and(Tenant::find('acme-ltd'))->toBeNull()            // nothing provisioned yet
        ->and(TenantLogin::tenantFor('jane@acme.test'))->toBeNull();

    Mail::assertSent(WorkspaceVerifyMail::class, fn (WorkspaceVerifyMail $m) => $m->hasTo('jane@acme.test'));
    Mail::assertNotSent(WorkspaceCredentialsMail::class);
});

it('provisions the workspace and emails credentials only after the email is confirmed', function () {
    $this->post('/register', ['org_name' => 'Acme Ltd', 'name' => 'Jane', 'email' => 'jane@acme.test']);
    $token = PendingSignup::query()->where('email', 'jane@acme.test')->value('token');

    $this->get(route('signup.verify', $token))->assertRedirect(route('signup.done'));

    expect(Tenant::find('acme-ltd'))->not->toBeNull()
        ->and(TenantLogin::tenantFor('jane@acme.test'))->toBe('acme-ltd')
        ->and(PendingSignup::query()->where('email', 'jane@acme.test')->exists())->toBeFalse(); // consumed

    Mail::assertSent(WorkspaceCredentialsMail::class, fn (WorkspaceCredentialsMail $m) => $m->hasTo('jane@acme.test'));

    Tenant::find('acme-ltd')->run(function () {
        $user = User::query()->sole();
        expect($user->email)->toBe('jane@acme.test')
            ->and($user->role)->toBe(UserRole::Admin)
            ->and($user->must_change_password)->toBeTrue()
            ->and($user->email_verified_at)->not->toBeNull();
    });
});

it('cannot provision twice from the same confirmation link', function () {
    $this->post('/register', ['org_name' => 'Acme Ltd', 'name' => 'Jane', 'email' => 'jane@acme.test']);
    $token = PendingSignup::query()->where('email', 'jane@acme.test')->value('token');

    $this->get(route('signup.verify', $token))->assertRedirect(route('signup.done'));
    // A second click on the now-consumed link lands on the expired screen.
    $this->get(route('signup.verify', $token))->assertOk()->assertSee(__('app.signup.expired_heading'));

    expect(Tenant::query()->count())->toBe(1);
});

it('rejects an expired confirmation token and provisions nothing', function () {
    $pending = PendingSignup::start('Beta Co', 'Bea', 'bea@beta.test');
    $pending->forceFill(['expires_at' => now()->subHour()])->save();

    $this->get(route('signup.verify', $pending->token))->assertOk()->assertSee(__('app.signup.expired_heading'));

    expect(Tenant::find('beta-co'))->toBeNull();
});

it('refuses a sign-up for an email that already owns a workspace', function () {
    TenantLogin::link('taken@x.test', 'some-tenant');

    $this->post('/register', ['org_name' => 'Beta Co', 'name' => 'B', 'email' => 'taken@x.test'])
        ->assertSessionHasErrors('email');

    expect(PendingSignup::query()->where('email', 'taken@x.test')->exists())->toBeFalse();
});

it('refuses a second sign-up while one is still pending for the same email', function () {
    PendingSignup::start('Beta Co', 'B', 'dup@x.test');

    $this->post('/register', ['org_name' => 'Beta Two', 'name' => 'B', 'email' => 'dup@x.test'])
        ->assertSessionHasErrors('email');
});

it('re-sends the verification link on request', function () {
    PendingSignup::start('Beta Co', 'B', 'bea@beta.test');
    Mail::fake(); // reset the count from start()

    $this->withSession(['signup_email' => 'bea@beta.test'])
        ->post('/register/resend')
        ->assertRedirect(route('signup.sent'));

    Mail::assertSent(WorkspaceVerifyMail::class, fn (WorkspaceVerifyMail $m) => $m->hasTo('bea@beta.test'));
});

it('notifies the operator of a new workspace when configured', function () {
    config(['niu.signup_admin_notify' => 'ops@cm-ea.com']);

    $this->post('/register', ['org_name' => 'Gamma SARL', 'name' => 'G', 'email' => 'g@gamma.test']);
    $token = PendingSignup::query()->where('email', 'g@gamma.test')->value('token');
    $this->get(route('signup.verify', $token));

    Mail::assertSent(NewWorkspaceAdminNotice::class, fn (NewWorkspaceAdminNotice $m) => $m->hasTo('ops@cm-ea.com'));
});

it('prunes expired pending sign-ups', function () {
    PendingSignup::start('Live Co', 'L', 'live@x.test'); // valid
    $old = PendingSignup::start('Old Co', 'O', 'old@x.test');
    $old->forceFill(['expires_at' => now()->subDay()])->save();

    $this->artisan('signup:prune-expired')->assertSuccessful();

    expect(PendingSignup::query()->where('email', 'old@x.test')->exists())->toBeFalse()
        ->and(PendingSignup::query()->where('email', 'live@x.test')->exists())->toBeTrue();
});

it('returns 404 for the sign-up routes when self-service is disabled', function () {
    config(['niu.self_service_signup' => false]);

    $this->get('/register')->assertNotFound();
    $this->post('/register', ['org_name' => 'X', 'name' => 'X', 'email' => 'x@x.test'])->assertNotFound();
});

it('renders the public wizard screens', function () {
    $this->get('/register')->assertOk()->assertSee(__('app.signup.org_name'));

    $this->withSession(['signup_email' => 'jane@acme.test'])
        ->get(route('signup.sent'))->assertOk()->assertSee(__('app.signup.sent_heading'));

    $this->withSession(['done_email' => 'jane@acme.test', 'done_password' => 'TEMPPASS1234', 'done_login' => url('/app/login')])
        ->get(route('signup.done'))->assertOk()->assertSee('TEMPPASS1234');
});

it('renders the credential and verification emails', function () {
    $credentials = new WorkspaceCredentialsMail('Jane', 'Acme', 'jane@acme.test', 'TEMPPASS1234', url('/app/login'));
    // The "Sign in" link pre-fills the recipient's email (?email=…).
    expect($credentials->render())->toContain('TEMPPASS1234')->toContain('jane@acme.test')
        ->toContain('email=jane%40acme.test');

    $verify = new WorkspaceVerifyMail('Jane', 'Acme', 'https://eace.cm-ea.test/onboarding/verify/abc', 24);
    expect($verify->render())->toContain('https://eace.cm-ea.test/onboarding/verify/abc');
});
