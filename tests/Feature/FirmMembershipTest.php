<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Domain\Firm\FirmRole;
use App\Models\AccountAssignment;
use App\Models\OrganizationProfile;
use App\Models\Tenant;
use App\Models\TenantLogin;
use App\Models\User;
use App\Services\Firm\FirmException;
use App\Services\Firm\FirmMembershipService;
use App\Services\Tenant\ClientProvisioningException;
use App\Services\Tenant\ClientProvisioningService;
use Illuminate\Support\Facades\Hash;

/*
 * Phase 1a — the accounting-firm umbrella data layer: firms own client
 * sub-accounts (isolated workspaces) and members; a member is mirrored into each
 * assigned sub-account with ONE password + ONE authenticator secret, so single
 * sign-in and a per-workspace switcher become possible. Isolation and the
 * cross-tenant-takeover guard are the invariants under test.
 */

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function firmSvc(): FirmMembershipService
{
    return app(FirmMembershipService::class);
}

it('creates a firm, a member and a client sub-account with its own buyer identity', function () {
    $svc = firmSvc();
    $firm = $svc->createFirm('Cabinet Comptable X', maxSubAccounts: 3);
    $added = $svc->addMember($firm, 'Awa', 'awa@cabinet.test', FirmRole::PowerUser);
    $sub = $svc->createSubAccount($firm, 'Client Alpha SARL', 'M058712345678X');

    expect($added->member->isPowerUser())->toBeTrue()
        ->and($added->temporaryPassword)->not->toBeNull()
        ->and((int) $sub->firm_id)->toBe((int) $firm->id)
        ->and($sub->name)->toBe('Client Alpha SARL');

    $niu = $sub->run(fn () => OrganizationProfile::current()->entity_niu);
    expect($niu)->toBe('M058712345678X');
});

it('enforces the firm sub-account cap', function () {
    $svc = firmSvc();
    $firm = $svc->createFirm('Small Firm', maxSubAccounts: 1);
    $svc->createSubAccount($firm, 'Client One');
    $svc->createSubAccount($firm, 'Client Two'); // over the cap
})->throws(FirmException::class);

it('rejects a member email already used by a standalone client', function () {
    app(ClientProvisioningService::class)->provision('Standalone SARL', 'Bob', 'bob@standalone.test');
    $firm = firmSvc()->createFirm('Firm');
    firmSvc()->addMember($firm, 'Bob', 'bob@standalone.test');
})->throws(FirmException::class);

it('mirrors one password and one 2FA secret across two assigned sub-accounts', function () {
    $svc = firmSvc();
    $firm = $svc->createFirm('Firm', maxSubAccounts: 5);
    $member = $svc->addMember($firm, 'Awa', 'awa@firm.test', FirmRole::Member, password: 'Secret-123')->member;

    // One authenticator secret enrolled centrally.
    $member->forceFill(['app_authentication_secret' => 'BASE32SECRET', 'must_change_password' => false])->save();

    $a = $svc->createSubAccount($firm, 'Client A');
    $b = $svc->createSubAccount($firm, 'Client B');
    $svc->assign($member, $a->id, UserRole::Admin);
    $svc->assign($member, $b->id, UserRole::Recorder);

    // The email resolves to BOTH workspaces.
    expect(TenantLogin::tenantsFor('awa@firm.test'))->toEqualCanonicalizing([$a->id, $b->id]);

    $ua = $a->run(fn () => User::query()->where('email', 'awa@firm.test')->first());
    $ub = $b->run(fn () => User::query()->where('email', 'awa@firm.test')->first());

    expect(Hash::check('Secret-123', (string) $ua->password))->toBeTrue()
        ->and(Hash::check('Secret-123', (string) $ub->password))->toBeTrue()
        ->and($ua->app_authentication_secret)->toBe('BASE32SECRET')
        ->and($ub->app_authentication_secret)->toBe('BASE32SECRET') // one authenticator entry
        ->and($ua->role)->toBe(UserRole::Admin)
        ->and($ub->role)->toBe(UserRole::Recorder);
});

it('refuses to assign a member to another firm\'s sub-account', function () {
    $svc = firmSvc();
    $firmA = $svc->createFirm('Firm A');
    $firmB = $svc->createFirm('Firm B');
    $member = $svc->addMember($firmA, 'X', 'x@a.test')->member;
    $subB = $svc->createSubAccount($firmB, 'B Client');
    $svc->assign($member, $subB->id);
})->throws(FirmException::class);

it('never exposes an unassigned workspace to a member', function () {
    $svc = firmSvc();
    $firm = $svc->createFirm('Firm', maxSubAccounts: 5);
    $member = $svc->addMember($firm, 'Awa', 'awa@firm.test')->member;
    $assigned = $svc->createSubAccount($firm, 'Assigned');
    $other = $svc->createSubAccount($firm, 'Not Assigned');
    $svc->assign($member, $assigned->id);

    expect(TenantLogin::tenantsFor('awa@firm.test'))->toBe([$assigned->id]);

    $hasUser = $other->run(fn () => User::query()->where('email', 'awa@firm.test')->exists());
    expect($hasUser)->toBeFalse();
});

it('syncs a password change across all the member\'s workspaces', function () {
    $svc = firmSvc();
    $firm = $svc->createFirm('Firm', maxSubAccounts: 5);
    $member = $svc->addMember($firm, 'Awa', 'awa@firm.test', password: 'Old-Password-1')->member;
    $a = $svc->createSubAccount($firm, 'A');
    $b = $svc->createSubAccount($firm, 'B');
    $svc->assign($member, $a->id);
    $svc->assign($member, $b->id);

    $member->forceFill(['password' => 'New-Password-2', 'must_change_password' => false])->save();
    $svc->syncCredentials($member);

    foreach ([$a, $b] as $t) {
        $u = $t->run(fn () => User::query()->where('email', 'awa@firm.test')->first());
        expect(Hash::check('New-Password-2', (string) $u->password))->toBeTrue()
            ->and(Hash::check('Old-Password-1', (string) $u->password))->toBeFalse();
    }
});

it('removes the tenant user and directory row on unassign', function () {
    $svc = firmSvc();
    $firm = $svc->createFirm('Firm', maxSubAccounts: 5);
    $member = $svc->addMember($firm, 'Awa', 'awa@firm.test')->member;
    $a = $svc->createSubAccount($firm, 'A');
    $svc->assign($member, $a->id);

    expect(TenantLogin::tenantsFor('awa@firm.test'))->toBe([$a->id]);

    $svc->unassign($member, $a->id);

    expect(TenantLogin::tenantsFor('awa@firm.test'))->toBe([])
        ->and(AccountAssignment::query()->where('firm_member_id', $member->id)->count())->toBe(0);
    $exists = $a->run(fn () => User::query()->where('email', 'awa@firm.test')->exists());
    expect($exists)->toBeFalse();
});

it('refuses to delete a firm that still owns sub-accounts', function () {
    $svc = firmSvc();
    $firm = $svc->createFirm('Firm');
    $svc->createSubAccount($firm, 'Client');
    $svc->deleteFirm($firm);
})->throws(FirmException::class);

it('keeps standalone clients on an exclusive single-workspace mapping', function () {
    $prov = app(ClientProvisioningService::class);
    $prov->provision('One SARL', 'A', 'a@one.test');

    expect(fn () => $prov->provision('Two SARL', 'A', 'a@one.test'))
        ->toThrow(ClientProvisioningException::class)
        ->and(TenantLogin::tenantsFor('a@one.test'))->toHaveCount(1);
});
