<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Domain\Firm\FirmRole;
use App\Filament\App\Pages\FirmConsole;
use App\Mail\WorkspaceCredentialsMail;
use App\Models\Firm;
use App\Models\FirmMember;
use App\Models\PendingSignup;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Firm\FirmMembershipService;
use App\Services\Tenant\ClientPasswordReset;
use App\Services\Tenant\ClientProvisioningService;
use App\Services\Tenant\SelfServiceOnboarding;
use App\Support\AccountSwitcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/*
 * The firm lifecycle end to end: self-service onboarding chooses company vs FIRM
 * (a firm gets an umbrella + its own home workspace and creates client
 * sub-accounts itself); credentials changed in any workspace sync back to the
 * central identity (Phase 1c); the operator can suspend a client and reset a
 * forgotten password from the admin console.
 */

afterEach(function () {
    Auth::guard('web')->logout();
    tenancy()->end();
    Tenant::all()->each->delete();
});

it('provisions a FIRM from self-service signup: umbrella + home workspace + power user', function () {
    Mail::fake();
    $pending = PendingSignup::start('Cabinet Fiduciaire Z', 'Zoe', 'zoe@cabinet-z.test', 'firm');

    $client = app(SelfServiceOnboarding::class)->completeFromToken($pending->token);

    expect($client)->not->toBeNull()
        ->and($client->temporaryPassword)->not->toBeNull();

    $firm = Firm::query()->where('name', 'Cabinet Fiduciaire Z')->firstOrFail();
    $member = FirmMember::query()->where('email', 'zoe@cabinet-z.test')->firstOrFail();

    expect($firm->max_sub_accounts)->toBe((int) config('niu.firm_trial_max_sub_accounts', 5))
        ->and($member->isPowerUser())->toBeTrue()
        ->and($member->must_change_password)->toBeTrue()
        ->and((int) $client->tenant->firm_id)->toBe((int) $firm->id) // the home workspace
        ->and(AccountSwitcher::workspacesFor(null))->toBe([]);

    // The power user is assigned to the home workspace and mirrored into it.
    $mirrored = $client->tenant->run(fn () => User::query()->where('email', 'zoe@cabinet-z.test')->exists());
    expect($mirrored)->toBeTrue();
});

it('keeps the plain COMPANY signup path unchanged', function () {
    Mail::fake();
    $pending = PendingSignup::start('Simple SARL', 'Sam', 'sam@simple.test'); // default: company

    $client = app(SelfServiceOnboarding::class)->completeFromToken($pending->token);

    expect($client)->not->toBeNull()
        ->and($client->tenant->firm_id)->toBeNull()
        ->and(Firm::query()->count())->toBe(0);
});

it('refuses a signup email already used by a firm member', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Firm');
    $svc->addMember($firm, 'Awa', 'awa@firm.test');

    expect(PendingSignup::emailAvailable('awa@firm.test'))->toBeFalse();
});

it('syncs a password changed inside ANY workspace back to the central identity and siblings (Phase 1c)', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Firm', maxSubAccounts: 5);
    $member = $svc->addMember($firm, 'Awa', 'awa@firm.test', password: 'Initial-1')->member;
    $a = $svc->createSubAccount($firm, 'Client A');
    $b = $svc->createSubAccount($firm, 'Client B');
    $svc->assign($member, $a->id);
    $svc->assign($member, $b->id);

    // The member changes their password while working in workspace B (the same
    // write the first-login ChangePassword page performs).
    $b->run(function (): void {
        User::query()->where('email', 'awa@firm.test')->firstOrFail()
            ->forceFill(['password' => Hash::make('Brand-New-2'), 'must_change_password' => false])
            ->save();
    });

    // Pushed back to the central identity…
    expect(Hash::check('Brand-New-2', FirmMember::query()->where('email', 'awa@firm.test')->firstOrFail()->passwordHash()))->toBeTrue();

    $inA = $a->run(fn () => User::query()->where('email', 'awa@firm.test')->firstOrFail()->getAttributes()['password']);
    expect(Hash::check('Brand-New-2', (string) $inA))->toBeTrue();
});

it('syncs a 2FA enrolment done inside a workspace to the central identity and siblings', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Firm', maxSubAccounts: 5);
    $member = $svc->addMember($firm, 'Awa', 'awa@firm.test', password: 'Initial-1')->member;
    $a = $svc->createSubAccount($firm, 'Client A');
    $b = $svc->createSubAccount($firm, 'Client B');
    $svc->assign($member, $a->id);
    $svc->assign($member, $b->id);

    $a->run(function (): void {
        User::query()->where('email', 'awa@firm.test')->firstOrFail()
            ->saveAppAuthenticationSecret('ENROLLED-SECRET');
    });

    expect(FirmMember::query()->where('email', 'awa@firm.test')->firstOrFail()->app_authentication_secret)->toBe('ENROLLED-SECRET');

    $inB = $b->run(fn () => User::query()->where('email', 'awa@firm.test')->firstOrFail()->app_authentication_secret);
    expect($inB)->toBe('ENROLLED-SECRET');
});

it('suspends a workspace: switcher hides it, switching is refused, pages are blocked', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Firm', maxSubAccounts: 5);
    $member = $svc->addMember($firm, 'Awa', 'awa@firm.test', password: 'Secret-1')->member;
    $a = $svc->createSubAccount($firm, 'Client A');
    $b = $svc->createSubAccount($firm, 'Client B');
    $svc->assign($member, $a->id);
    $svc->assign($member, $b->id);

    $b->refresh()->suspend();
    expect($b->refresh()->isSuspended())->toBeTrue();

    tenancy()->initialize($a);
    $user = User::query()->where('email', 'awa@firm.test')->firstOrFail();
    Auth::guard('web')->login($user);
    session()->put('tenant_id', $a->id);

    expect(collect(AccountSwitcher::workspacesFor($user))->pluck('id')->all())->toBe([$a->id])
        ->and(AccountSwitcher::switchTo($b->id))->toBeFalse();

    // A signed-in session pinned to a suspended workspace is blocked outright.
    tenancy()->end();
    $a->refresh()->suspend();
    $this->actingAs($user)->withSession(['tenant_id' => $a->id])->get('/app')->assertForbidden();
});

it('suspends every sub-account at once when the firm is deactivated', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Firm', maxSubAccounts: 5);
    $sub = $svc->createSubAccount($firm, 'Client A');

    expect($sub->isSuspended())->toBeFalse();

    $firm->update(['is_active' => false]);

    expect($sub->refresh()->isSuspended())->toBeTrue();
});

it('resets a standalone client user password from the admin console (emailed + must change)', function () {
    Mail::fake();
    $client = app(ClientProvisioningService::class)->provision('Solo SARL', 'Solo', 'solo@solo.test');

    $temporary = app(ClientPasswordReset::class)->reset($client->tenant, 'solo@solo.test');

    $stored = $client->tenant->run(fn () => User::query()->where('email', 'solo@solo.test')->firstOrFail());
    expect(Hash::check($temporary, (string) $stored->getAttributes()['password']))->toBeTrue()
        ->and($stored->must_change_password)->toBeTrue();

    Mail::assertSent(WorkspaceCredentialsMail::class, fn (WorkspaceCredentialsMail $m): bool => $m->hasTo('solo@solo.test'));
});

it('resets a FIRM member password centrally — every workspace at once', function () {
    Mail::fake();
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Firm', maxSubAccounts: 5);
    $member = $svc->addMember($firm, 'Awa', 'awa@firm.test', password: 'Old-1')->member;
    $a = $svc->createSubAccount($firm, 'Client A');
    $b = $svc->createSubAccount($firm, 'Client B');
    $svc->assign($member, $a->id);
    $svc->assign($member, $b->id);

    $temporary = app(ClientPasswordReset::class)->reset($a, 'awa@firm.test');

    expect(Hash::check($temporary, $member->refresh()->passwordHash()))->toBeTrue();
    foreach ([$a, $b] as $t) {
        $hash = $t->run(fn () => (string) User::query()->where('email', 'awa@firm.test')->firstOrFail()->getAttributes()['password']);
        expect(Hash::check($temporary, $hash))->toBeTrue();
    }
});

it('opens the firm console to power users only', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Firm', maxSubAccounts: 5);
    $power = $svc->addMember($firm, 'Zoe', 'zoe@firm.test', FirmRole::PowerUser, password: 'x-1')->member;
    $plain = $svc->addMember($firm, 'Awa', 'awa@firm.test', FirmRole::Member, password: 'x-2')->member;
    $home = $svc->createSubAccount($firm, 'Home');
    $svc->assign($power, $home->id, UserRole::Admin);
    $svc->assign($plain, $home->id, UserRole::Recorder);

    tenancy()->initialize($home);

    Auth::guard('web')->login(User::query()->where('email', 'zoe@firm.test')->firstOrFail());
    expect(FirmConsole::canAccess())->toBeTrue();

    Auth::guard('web')->login(User::query()->where('email', 'awa@firm.test')->firstOrFail());
    expect(FirmConsole::canAccess())->toBeFalse();

    // Deactivated firm → console closes even for the power user.
    Auth::guard('web')->login(User::query()->where('email', 'zoe@firm.test')->firstOrFail());
    $firm->update(['is_active' => false]);
    expect(FirmConsole::canAccess())->toBeFalse();
});
