<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Models\Firm;
use App\Models\FirmMember;
use App\Models\Tenant;
use App\Models\TenantLogin;
use App\Models\User;
use App\Services\Firm\FirmMembershipService;
use App\Services\Tenant\ClientProvisioningService;
use App\Support\AccountSwitcher;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;

/*
 * Phase 1b — the firm member signs in once (to a deterministic primary workspace)
 * and hops between their assigned client sub-accounts via the switcher, with every
 * switch re-verified against their assignments. Isolation is the invariant: a
 * member can never switch into a workspace they aren't assigned to.
 */

afterEach(function () {
    Auth::guard('web')->logout();
    tenancy()->end();
    Tenant::all()->each->delete();
});

/**
 * @return array{0: Firm, 1: FirmMember, 2: Tenant, 3: Tenant}
 */
function switcherSetup(): array
{
    $svc = app(FirmMembershipService::class);
    $firm = $svc->createFirm('Firm', maxSubAccounts: 5);
    $member = $svc->addMember($firm, 'Awa', 'awa@firm.test', password: 'Secret-123')->member;
    $alpha = $svc->createSubAccount($firm, 'Alpha Client'); // -> alpha-client
    $beta = $svc->createSubAccount($firm, 'Beta Client');   // -> beta-client
    $svc->assign($member, $alpha->id, UserRole::Admin);
    $svc->assign($member, $beta->id, UserRole::Recorder);

    return [$firm, $member, $alpha, $beta];
}

function actAsMemberIn(Tenant $tenant): User
{
    tenancy()->initialize($tenant);
    $user = User::query()->where('email', 'awa@firm.test')->firstOrFail();
    Auth::guard('web')->login($user);
    session()->put('tenant_id', $tenant->getTenantKey());

    return $user;
}

it('resolves a firm member to a deterministic primary workspace at login', function () {
    [, , $alpha, $beta] = switcherSetup();

    expect(TenantLogin::tenantFor('awa@firm.test'))->toBe($alpha->id) // alphabetically first
        ->and(TenantLogin::tenantsFor('awa@firm.test'))->toEqualCanonicalizing([$alpha->id, $beta->id]);
});

it('lists the member\'s assigned workspaces with the current one flagged', function () {
    [, , $alpha, $beta] = switcherSetup();
    $user = actAsMemberIn($alpha);

    $ws = AccountSwitcher::workspacesFor($user);

    expect($ws)->toHaveCount(2)
        ->and(collect($ws)->firstWhere('current', true)['id'])->toBe($alpha->id)
        ->and(collect($ws)->pluck('id')->all())->toEqualCanonicalizing([$alpha->id, $beta->id]);
});

it('switches a member to another assigned workspace', function () {
    [, , $alpha, $beta] = switcherSetup();
    actAsMemberIn($alpha);

    $ok = AccountSwitcher::switchTo($beta->id);

    expect($ok)->toBeTrue()
        ->and(session('tenant_id'))->toBe($beta->id)
        ->and((string) tenant()?->getTenantKey())->toBe($beta->id)
        ->and(Auth::user()?->getAttribute('email'))->toBe('awa@firm.test');
});

it('refuses to switch to a workspace the member is not assigned to', function () {
    [$firm, , $alpha] = switcherSetup();
    $gamma = app(FirmMembershipService::class)->createSubAccount($firm, 'Gamma Client'); // not assigned
    actAsMemberIn($alpha);

    $ok = AccountSwitcher::switchTo($gamma->id);

    expect($ok)->toBeFalse()
        ->and(session('tenant_id'))->toBe($alpha->id); // unchanged
});

it('offers no switch for a standalone client', function () {
    $client = app(ClientProvisioningService::class)->provision('Solo SARL', 'Solo', 'solo@solo.test');
    tenancy()->initialize($client->tenant);
    $user = User::query()->where('email', 'solo@solo.test')->firstOrFail();
    Auth::guard('web')->login($user);
    session()->put('tenant_id', $client->tenant->getTenantKey());

    expect(AccountSwitcher::isFirmMember($user))->toBeFalse()
        ->and(AccountSwitcher::workspacesFor($user))->toBe([])
        ->and(AccountSwitcher::switchTo($client->tenant->getTenantKey()))->toBeFalse();
});

it('switches via the HTTP route and lands back on /app', function () {
    [, , $alpha, $beta] = switcherSetup();
    tenancy()->initialize($alpha);
    $user = User::query()->where('email', 'awa@firm.test')->firstOrFail();
    tenancy()->end();

    $this->withoutMiddleware(ThrottleRequests::class);

    $this->actingAs($user)
        ->withSession(['tenant_id' => $alpha->id])
        ->post('/workspace/switch', ['tenant' => $beta->id])
        ->assertRedirect('/app')
        ->assertSessionHas('tenant_id', $beta->id);
});
