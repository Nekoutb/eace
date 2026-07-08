<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Domain\Firm\FirmRole;
use App\Exceptions\ReadOnlyImpersonationException;
use App\Filament\App\Pages\FirmConsole\FirmTeamWidget;
use App\Filament\Auth\Login;
use App\Mail\WorkspaceCredentialsMail;
use App\Models\AccountAssignment;
use App\Models\DataAccessLog;
use App\Models\FirmMember;
use App\Models\OrganizationProfile;
use App\Models\Tenant;
use App\Models\TenantLogin;
use App\Models\User;
use App\Services\Firm\FirmMembershipService;
use App\Support\AccountSwitcher;
use App\Support\Impersonation;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * REGRESSION — "email not recognised" after a firm invite. Inviting a member
 * created their central identity but assigned NO client account, so their welcome
 * email led to a sign-in that could not resolve any workspace. The invite now
 * REQUIRES at least one account (access granted before the email goes out), and a
 * member who somehow has none gets a truthful message instead of "not recognised".
 */

beforeEach(fn () => Mail::fake());

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

/** A firm with a home workspace and its signed-in power user; returns [firm, home, powerUser]. */
function firmWithPowerUser(): array
{
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Cabinet Invite Test', maxSubAccounts: 5);
    $power = $svc->addMember($firm, 'Power', 'power@invite.test', FirmRole::PowerUser, password: 'Power-Pass-1')->member;
    $home = $svc->createSubAccount($firm, 'Cabinet Invite Test');
    $svc->assign($power, (string) $home->getTenantKey(), UserRole::Admin);

    tenancy()->initialize($home);
    $user = User::query()->where('email', 'power@invite.test')->firstOrFail();

    return [$firm, $home, $user];
}

it('invites a member WITH account access so their welcome email just works', function () {
    [$firm, $home, $powerUser] = firmWithPowerUser();
    test()->actingAs($powerUser);
    Filament::setCurrentPanel('app');

    Livewire::test(FirmTeamWidget::class)
        ->callTableAction('addMember', data: [
            'name' => 'Nouvelle Collègue',
            'email' => 'collegue@invite.test',
            'role' => FirmRole::Member->value,
            'accounts' => [(string) $home->getTenantKey()],
            'account_role' => UserRole::Recorder->value,
        ])
        ->assertHasNoTableActionErrors();

    // Access exists BEFORE the (queued) email lands: the sign-in resolves.
    expect(TenantLogin::tenantsFor('collegue@invite.test'))->toBe([(string) $home->getTenantKey()])
        ->and(AccountAssignment::query()->whereHas('member', fn ($q) => $q->where('email', 'collegue@invite.test'))->count())->toBe(1);

    Mail::assertQueued(WorkspaceCredentialsMail::class, fn (WorkspaceCredentialsMail $m) => $m->hasTo('collegue@invite.test'));

    $mirrored = $home->run(fn () => User::query()->where('email', 'collegue@invite.test')->exists());
    expect($mirrored)->toBeTrue();
});

it('requires at least one client account on the invite form', function () {
    [, , $powerUser] = firmWithPowerUser();
    test()->actingAs($powerUser);
    Filament::setCurrentPanel('app');

    Livewire::test(FirmTeamWidget::class)
        ->callTableAction('addMember', data: [
            'name' => 'Sans Compte',
            'email' => 'sans@invite.test',
            'role' => FirmRole::Member->value,
            'accounts' => [],
            'account_role' => UserRole::Recorder->value,
        ])
        ->assertHasTableActionErrors(['accounts']);

    expect(FirmMember::query()->where('email', 'sans@invite.test')->exists())->toBeFalse();
});

it('tells an unassigned firm member the TRUTH at sign-in instead of "not recognised"', function () {
    // A member who exists centrally but holds no account (legacy invite / all
    // access revoked) — the login must say why, not pretend the email is unknown.
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Cabinet Orphan');
    $svc->addMember($firm, 'Orpheline', 'orpheline@invite.test', FirmRole::Member, password: 'Some-Pass-1');

    Filament::setCurrentPanel('app');

    Livewire::test(Login::class)
        ->fillForm(['email' => 'orpheline@invite.test', 'password' => 'Some-Pass-1'])
        ->call('authenticate')
        ->assertHasFormErrors()
        ->assertSee(__('firm.login_no_access'));
});

/*
 * Assignment consistency: workspace access granted through ANY path must record
 * the assignment that drives the member's switcher. The reported case: a member
 * added via the workspace's own Users screen had access but no assignment — so
 * "he does not have the selector".
 */

it('records the assignment when a firm member is added via the workspace Users screen', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Cabinet Sync');
    $member = $svc->addMember($firm, 'Idris', 'idris@sync.test', FirmRole::Member, password: 'Pass-123456')->member;
    $home = $svc->createSubAccount($firm, 'Sync Client One');

    // The workspace-admin path: a plain tenant User created inside the workspace.
    tenancy()->initialize($home);
    User::query()->create([
        'name' => 'Idris', 'email' => 'idris@sync.test',
        'password' => Hash::make('Pass-123456'),
        'role' => UserRole::Settler,
    ]);
    tenancy()->end();

    $assignment = AccountAssignment::query()
        ->where('firm_member_id', $member->getKey())
        ->where('tenant_id', (string) $home->getTenantKey())
        ->first();

    expect($assignment)->not->toBeNull()
        ->and($assignment->account_role)->toBe(UserRole::Settler)  // role follows the user row
        ->and(AccountSwitcher::workspacesFor(
            $home->run(fn () => User::query()->where('email', 'idris@sync.test')->first())
        ))->toHaveCount(1); // the switcher now sees it
});

it('drops the assignment when the workspace user is deleted', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Cabinet Sync');
    $member = $svc->addMember($firm, 'Idris', 'idris@sync.test', FirmRole::Member, password: 'Pass-123456')->member;
    $home = $svc->createSubAccount($firm, 'Sync Client One');
    $svc->assign($member, (string) $home->getTenantKey(), UserRole::Recorder);

    $home->run(fn () => User::query()->where('email', 'idris@sync.test')->get()->each->delete());

    expect(AccountAssignment::query()->where('firm_member_id', $member->getKey())->count())->toBe(0);
});

it('never creates a cross-firm assignment from an unrelated workspace user row', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Cabinet Sync');
    $member = $svc->addMember($firm, 'Idris', 'idris@sync.test', FirmRole::Member, password: 'Pass-123456')->member;
    $other = Tenant::create(['id' => 'standalone-sync', 'name' => 'Standalone']); // no firm

    tenancy()->initialize($other);
    User::query()->create([
        'name' => 'Idris', 'email' => 'idris@sync.test',
        'password' => Hash::make('x'),
        'role' => UserRole::Recorder,
    ]);
    tenancy()->end();

    expect(AccountAssignment::query()->where('firm_member_id', $member->getKey())->count())->toBe(0);
});

/*
 * The switcher's POST route end-to-end: a power user pinned to workspace A picks
 * assigned workspace B — the swap must happen (reported: clicks 302 back with no
 * switch and no audit entry ever recorded).
 */

it('switches an assigned member between workspaces via the real POST route', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Cabinet Switch');
    $member = $svc->addMember($firm, 'P', 'p@switch.test', FirmRole::PowerUser, password: 'Pass-123456')->member;
    $a = $svc->createSubAccount($firm, 'Switch Alpha');
    $b = $svc->createSubAccount($firm, 'Switch Beta');
    $svc->assign($member, (string) $a->getTenantKey(), UserRole::Admin);
    $svc->assign($member, (string) $b->getTenantKey(), UserRole::Admin);

    tenancy()->initialize($a);
    $userA = User::query()->where('email', 'p@switch.test')->firstOrFail();
    tenancy()->end();

    $this->actingAs($userA)
        ->withSession(['tenant_id' => (string) $a->getTenantKey()])
        ->post('/workspace/switch', ['tenant' => (string) $b->getTenantKey()])
        ->assertRedirect('/app');

    expect(session('tenant_id'))->toBe((string) $b->getTenantKey())          // pinned to B
        ->and((string) auth()->user()->email)->toBe('p@switch.test')          // still the member
        ->and(DataAccessLog::query()->where('action', 'workspace_switch')->count())->toBe(1); // audited
});

it('tells the member plainly when a switch target is no longer available', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Cabinet Switch Fail');
    $member = $svc->addMember($firm, 'P', 'pf@switch.test', FirmRole::PowerUser, password: 'Pass-123456')->member;
    $a = $svc->createSubAccount($firm, 'Fail Alpha');
    $svc->assign($member, (string) $a->getTenantKey(), UserRole::Admin);

    tenancy()->initialize($a);
    $userA = User::query()->where('email', 'pf@switch.test')->firstOrFail();
    tenancy()->end();

    // The clicked account was deleted after the page rendered (stale selector).
    $this->actingAs($userA)
        ->withSession(['tenant_id' => (string) $a->getTenantKey()])
        ->post('/workspace/switch', ['tenant' => 'deleted-or-never-assigned'])
        ->assertRedirect('/app');

    // The user is told, not silently bounced; their context is unchanged.
    expect(collect(session('filament.notifications', []))->isNotEmpty())->toBeTrue()
        ->and(session('tenant_id'))->toBe((string) $a->getTenantKey());
});

it('switches via the URL parameter even when the form body is stripped in transit', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Cabinet Param');
    $member = $svc->addMember($firm, 'P', 'pp@switch.test', FirmRole::PowerUser, password: 'Pass-123456')->member;
    $a = $svc->createSubAccount($firm, 'Param Alpha');
    $b = $svc->createSubAccount($firm, 'Param Beta');
    $svc->assign($member, (string) $a->getTenantKey(), UserRole::Admin);
    $svc->assign($member, (string) $b->getTenantKey(), UserRole::Admin);

    tenancy()->initialize($a);
    $userA = User::query()->where('email', 'pp@switch.test')->firstOrFail();
    tenancy()->end();

    // EMPTY body — the reported live failure mode; the URL alone must carry it.
    $this->actingAs($userA)
        ->withSession(['tenant_id' => (string) $a->getTenantKey()])
        ->post('/workspace/switch/'.$b->getTenantKey(), [])
        ->assertRedirect('/app');

    expect(session('tenant_id'))->toBe((string) $b->getTenantKey());
});

/*
 * THE production failure: session-based auth (what real browsers do — actingAs()
 * bypasses it) on the switch route. Laravel's middleware priority used to run
 * `auth` BEFORE the tenancy initializer, resolving the session's user id against
 * the CENTRAL users table — wrong or missing user, so the switch NEVER worked
 * live. The priority override must keep tenancy first.
 */

it('switches with real session-based auth (the exact production failure mode)', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Cabinet Session');
    $member = $svc->addMember($firm, 'S', 'sess@switch.test', FirmRole::PowerUser, password: 'Pass-123456')->member;
    $a = $svc->createSubAccount($firm, 'Session Alpha');
    $b = $svc->createSubAccount($firm, 'Session Beta');
    $svc->assign($member, (string) $a->getTenantKey(), UserRole::Admin);
    $svc->assign($member, (string) $b->getTenantKey(), UserRole::Admin);

    tenancy()->initialize($a);
    $userId = User::query()->where('email', 'sess@switch.test')->value('id');
    tenancy()->end();

    // The REAL session state a signed-in browser carries — no actingAs().
    $guardSessionKey = 'login_web_'.sha1(SessionGuard::class);

    $this->withSession([
        $guardSessionKey => $userId,
        'tenant_id' => (string) $a->getTenantKey(),
    ])->post('/workspace/switch/'.$b->getTenantKey())
        ->assertRedirect('/app');

    expect(session('tenant_id'))->toBe((string) $b->getTenantKey())
        ->and(DataAccessLog::query()->where('action', 'workspace_switch')->count())->toBe(1);
});

it('lets a badged-in operator switch workspaces READ-ONLY (the only way to test the firm view)', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Cabinet Imp');
    $member = $svc->addMember($firm, 'I', 'imp@switch.test', FirmRole::PowerUser, password: 'Pass-123456')->member;
    $a = $svc->createSubAccount($firm, 'Imp Alpha');
    $b = $svc->createSubAccount($firm, 'Imp Beta');
    $svc->assign($member, (string) $a->getTenantKey(), UserRole::Admin);
    $svc->assign($member, (string) $b->getTenantKey(), UserRole::Admin);

    tenancy()->initialize($a);
    $userA = User::query()->where('email', 'imp@switch.test')->firstOrFail();
    tenancy()->end();

    // Badged into workspace A (read-only impersonation marker set).
    $this->actingAs($userA)->withSession([
        'tenant_id' => (string) $a->getTenantKey(),
        'impersonation' => ['admin_id' => 1, 'tenant' => (string) $a->getTenantKey(), 'tenant_name' => 'Imp Alpha', 'started_at' => now()->timestamp],
    ])->post('/workspace/switch/'.$b->getTenantKey())
        ->assertRedirect('/app');

    // Switched to B, but STILL read-only: the impersonation follows, retargeted to B,
    // and it is audited as an impersonation move (not a normal member switch).
    expect(session('tenant_id'))->toBe((string) $b->getTenantKey())
        ->and(session('impersonation.tenant'))->toBe((string) $b->getTenantKey())
        ->and(Impersonation::readOnly())->toBeTrue()
        ->and(DataAccessLog::query()->where('action', 'impersonate_switch')->count())->toBe(1)
        ->and(DataAccessLog::query()->where('action', 'workspace_switch')->count())->toBe(0);

    session()->forget('impersonation'); // clear before teardown
});

/*
 * The read-only guard's exemption (the mechanism behind the impersonation switch):
 * while impersonating, tenant-data writes are blocked, but the CENTRAL audit log
 * — which records the impersonation itself, including a read-only switch — is not.
 */

it('read-only guard blocks tenant writes yet allows the central audit log', function () {
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Cabinet Guard');
    $sub = $svc->createSubAccount($firm, 'Guard Client');

    tenancy()->initialize($sub);
    session()->put('impersonation', ['admin_id' => 1, 'tenant' => (string) $sub->getTenantKey(), 'tenant_name' => 'Guard Client', 'started_at' => now()->timestamp]);

    // Arm the guard exactly as the TenancyInitialized listener does.
    Impersonation::guard(DB::connection());

    // The compliance audit log is exempt — the impersonation must be recordable.
    DataAccessLog::record('probe_audit', 'tenant:'.$sub->getTenantKey());
    expect(DataAccessLog::query()->where('action', 'probe_audit')->count())->toBe(1);

    // Any other tenant-data write is rejected.
    expect(fn () => OrganizationProfile::current()->update(['entity_name' => 'HACK']))
        ->toThrow(ReadOnlyImpersonationException::class);

    session()->forget('impersonation');
    tenancy()->end();
});
