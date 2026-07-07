<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Mail\NewWorkspaceAdminNotice;
use App\Mail\WorkspaceCredentialsMail;
use App\Mail\WorkspaceVerifyMail;
use App\Models\Firm;
use App\Models\PendingSignup;
use App\Models\Tenant;
use App\Models\TenantLogin;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
 * Self-service onboarding is INSTANT: the workspace is provisioned on submission,
 * the one-time credentials are shown on screen right away, and the welcome email
 * (same credentials) is QUEUED as the courtesy copy — no fragile confirmation
 * link, no waiting on SMTP in front of a client. The old verify-first token path
 * stays working for links already in inboxes.
 */

beforeEach(function () {
    config(['niu.self_service_signup' => true]);
    Mail::fake();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each->delete();
    foreach (['acme-ltd', 'beta-co', 'gamma-sarl', 'cabinet-onboard-test'] as $id) {
        $file = database_path('tenant'.$id.'.sqlite');
        if (is_file($file)) {
            @unlink($file);
        }
    }
});

it('provisions the workspace INSTANTLY and shows the credentials on screen', function () {
    $response = $this->post('/register', [
        'org_name' => 'Acme Ltd', 'name' => 'Jane', 'email' => 'jane@acme.test', 'consent' => '1',
    ]);

    $response->assertRedirect(route('signup.done'))
        ->assertSessionHas('done_email', 'jane@acme.test')
        ->assertSessionHas('done_password');

    expect(Tenant::find('acme-ltd'))->not->toBeNull()                       // provisioned NOW
        ->and(TenantLogin::tenantFor('jane@acme.test'))->toBe('acme-ltd')
        ->and(PendingSignup::query()->where('email', 'jane@acme.test')->exists())->toBeFalse(); // no pending step

    // The welcome email is queued (never blocks the screen) and carries credentials.
    Mail::assertQueued(WorkspaceCredentialsMail::class, fn (WorkspaceCredentialsMail $m) => $m->hasTo('jane@acme.test'));
    Mail::assertNotSent(WorkspaceVerifyMail::class);

    Tenant::find('acme-ltd')->run(function () {
        $user = User::query()->sole();
        expect($user->email)->toBe('jane@acme.test')
            ->and($user->role)->toBe(UserRole::Admin)
            ->and($user->must_change_password)->toBeTrue(); // 2FA + new password on first sign-in
    });
});

it('provisions a FIRM instantly from the sign-up choice', function () {
    $this->post('/register', [
        'account_type' => 'firm',
        'org_name' => 'Cabinet Onboard Test', 'name' => 'Zoe', 'email' => 'zoe@cabinet-onboard.test', 'consent' => '1',
    ])->assertRedirect(route('signup.done'))->assertSessionHas('done_password');

    $firm = Firm::query()->where('name', 'Cabinet Onboard Test')->firstOrFail();
    $home = Tenant::find('cabinet-onboard-test');

    expect($home)->not->toBeNull()
        ->and((int) $home->firm_id)->toBe((int) $firm->id)
        ->and($home->consent_version)->not->toBeNull() // consent stamped on the workspace
        ->and(TenantLogin::tenantFor('zoe@cabinet-onboard.test'))->toBe('cabinet-onboard-test');

    Mail::assertQueued(WorkspaceCredentialsMail::class, fn (WorkspaceCredentialsMail $m) => $m->hasTo('zoe@cabinet-onboard.test'));
});

it('still honours a LEGACY verification link already in an inbox', function () {
    $pending = PendingSignup::start('Beta Co', 'Bea', 'bea@beta.test');

    $this->get(route('signup.verify', $pending->token))->assertRedirect(route('signup.done'));

    expect(Tenant::find('beta-co'))->not->toBeNull()
        ->and(TenantLogin::tenantFor('bea@beta.test'))->toBe('beta-co')
        ->and(PendingSignup::query()->where('email', 'bea@beta.test')->exists())->toBeFalse(); // consumed

    Mail::assertQueued(WorkspaceCredentialsMail::class, fn (WorkspaceCredentialsMail $m) => $m->hasTo('bea@beta.test'));

    Tenant::find('beta-co')->run(function () {
        expect(User::query()->sole()->email_verified_at)->not->toBeNull(); // that path proved the email
    });
});

it('cannot provision twice from the same legacy confirmation link', function () {
    $pending = PendingSignup::start('Beta Co', 'Bea', 'bea@beta.test');

    $this->get(route('signup.verify', $pending->token))->assertRedirect(route('signup.done'));
    // A second click on the now-consumed link lands on the expired screen.
    $this->get(route('signup.verify', $pending->token))->assertOk()->assertSee(__('app.signup.expired_heading'));

    expect(Tenant::query()->count())->toBe(1);
});

it('rejects an expired legacy token and provisions nothing', function () {
    $pending = PendingSignup::start('Beta Co', 'Bea', 'bea@beta.test');
    $pending->forceFill(['expires_at' => now()->subHour()])->save();

    $this->get(route('signup.verify', $pending->token))->assertOk()->assertSee(__('app.signup.expired_heading'));

    expect(Tenant::find('beta-co'))->toBeNull();
});

it('refuses a sign-up for an email that already owns a workspace', function () {
    TenantLogin::link('taken@x.test', 'some-tenant');

    $this->post('/register', ['org_name' => 'Beta Co', 'name' => 'B', 'email' => 'taken@x.test', 'consent' => '1'])
        ->assertSessionHasErrors('email');

    expect(Tenant::find('beta-co'))->toBeNull();
});

it('re-sends the legacy verification link on request', function () {
    PendingSignup::start('Beta Co', 'B', 'bea@beta.test');
    Mail::fake(); // reset

    $this->withSession(['signup_email' => 'bea@beta.test'])
        ->post('/register/resend')
        ->assertRedirect(route('signup.sent'));

    Mail::assertSent(WorkspaceVerifyMail::class, fn (WorkspaceVerifyMail $m) => $m->hasTo('bea@beta.test'));
});

it('notifies the operator of a new workspace when configured', function () {
    config(['niu.signup_admin_notify' => 'ops@cm-ea.com']);

    $this->post('/register', ['org_name' => 'Gamma SARL', 'name' => 'G', 'email' => 'g@gamma.test', 'consent' => '1'])
        ->assertRedirect(route('signup.done'));

    Mail::assertQueued(NewWorkspaceAdminNotice::class, fn (NewWorkspaceAdminNotice $m) => $m->hasTo('ops@cm-ea.com'));
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

it('renders the welcome (credentials) and verification emails', function () {
    $credentials = new WorkspaceCredentialsMail('Jane', 'Acme', 'jane@acme.test', 'TEMPPASS1234', url('/app/login'));
    // The "Sign in" link pre-fills the recipient's email (?email=…).
    expect($credentials->render())->toContain('TEMPPASS1234')->toContain('jane@acme.test')
        ->toContain('email=jane%40acme.test');

    $verify = new WorkspaceVerifyMail('Jane', 'Acme', 'https://eace.cm-ea.test/onboarding/verify/abc', 24);
    expect($verify->render())->toContain('https://eace.cm-ea.test/onboarding/verify/abc');
});
