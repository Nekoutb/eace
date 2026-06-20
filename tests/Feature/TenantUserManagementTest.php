<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\App\Pages\ChangePassword;
use App\Filament\App\Pages\Settings;
use App\Filament\App\Resources\Users\UserResource;
use App\Filament\App\Widgets\GettingStarted;
use App\Http\Middleware\EnforcePasswordRotation;
use App\Http\Middleware\ForcePasswordChange;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'um']);
    tenancy()->initialize($this->tenant);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function makeUser(UserRole $role, array $overrides = []): User
{
    static $n = 0;
    $n++;

    return User::query()->create(array_merge([
        'name' => 'User '.$n,
        'email' => "user{$n}@um.test",
        'password' => Hash::make('secret123'),
        'role' => $role,
    ], $overrides));
}

it('lets admins reach user management and settings', function () {
    test()->actingAs(makeUser(UserRole::Admin));

    expect(UserResource::canAccess())->toBeTrue()
        ->and(Settings::canAccess())->toBeTrue();
});

it('hides user management and settings from non-admins', function () {
    test()->actingAs(makeUser(UserRole::Recorder));

    expect(UserResource::canAccess())->toBeFalse()
        ->and(UserResource::shouldRegisterNavigation())->toBeFalse()
        ->and(Settings::canAccess())->toBeFalse();
});

it('treats a sole admin as the last admin but not once a second exists', function () {
    $admin = makeUser(UserRole::Admin);
    expect($admin->isLastAdmin())->toBeTrue();

    makeUser(UserRole::Admin);
    expect($admin->fresh()->isLastAdmin())->toBeFalse();
});

it('never treats a non-admin as the last admin', function () {
    makeUser(UserRole::Admin);
    $recorder = makeUser(UserRole::Recorder);

    expect($recorder->isLastAdmin())->toBeFalse();
});

it('redirects a temporary-password user to the change-password page', function () {
    $user = makeUser(UserRole::Recorder, ['must_change_password' => true]);

    $request = Request::create('/app');
    $request->setUserResolver(fn () => $user);

    $response = (new ForcePasswordChange)->handle($request, fn () => response('reached'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('change-password');
});

it('lets a temporary-password user reach the 2FA enrolment pages (no redirect loop)', function () {
    // 2FA enrolment runs FIRST: the MFA gate sends a no-2FA user to set-up, so the
    // change-password redirect must exempt those routes or the two gates loop
    // forever (ERR_TOO_MANY_REDIRECTS).
    $user = makeUser(UserRole::Recorder, ['must_change_password' => true]);

    $request = Request::create('/app/multi-factor-authentication/set-up');
    $request->setUserResolver(fn () => $user);

    $response = (new ForcePasswordChange)->handle($request, fn () => response('reached'));

    expect($response->getContent())->toBe('reached');
});

it('lets a settled user through the password-change gate', function () {
    $user = makeUser(UserRole::Admin, ['must_change_password' => false]);

    $request = Request::create('/app');
    $request->setUserResolver(fn () => $user);

    $response = (new ForcePasswordChange)->handle($request, fn () => response('reached'));

    expect($response->getContent())->toBe('reached');
});

it('stamps last_login_at when a tenant user signs in', function () {
    $user = makeUser(UserRole::Recorder);
    expect($user->last_login_at)->toBeNull();

    // Mirror real login: the auth provider hands the listener a freshly loaded
    // row (so the last_login_at column is present in the model's attributes).
    event(new Login('web', User::query()->find($user->getKey()), false));

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('confines tenant users to the app panel and central users to the admin panel', function () {
    $appPanel = Filament::getPanel('app');
    $adminPanel = Filament::getPanel('admin');

    $tenantUser = new User(['role' => UserRole::Recorder]); // has the role attribute
    $centralUser = new User(['name' => 'Landlord']);        // no role attribute

    expect($tenantUser->canAccessPanel($appPanel))->toBeTrue()
        ->and($tenantUser->canAccessPanel($adminPanel))->toBeFalse()
        ->and($centralUser->canAccessPanel($adminPanel))->toBeTrue()
        ->and($centralUser->canAccessPanel($appPanel))->toBeFalse();
});

it('shows and renders the getting-started card for a brand-new workspace', function () {
    test()->actingAs(makeUser(UserRole::Admin));
    Filament::setCurrentPanel(Filament::getPanel('app'));

    // Empty workspace → the card is shown and renders (its CTA links resolve).
    expect(GettingStarted::canView())->toBeTrue();
    Livewire::test(GettingStarted::class)->assertOk();
});

it('blocks privileged Livewire actions for a temp-password user but allows the setup flows', function () {
    $user = makeUser(UserRole::Admin, ['must_change_password' => true]);

    $privileged = Request::create('/livewire/update', 'POST', [
        'components' => [['snapshot' => json_encode(['memo' => ['name' => 'app.filament.app.pages.settings', 'path' => 'app/settings']])]],
    ]);
    $privileged->setUserResolver(fn () => $user);

    expect(fn () => (new EnforcePasswordRotation)->handle($privileged, fn () => response('ran')))
        ->toThrow(HttpException::class);

    // The change-password form is allowed.
    $changeForm = Request::create('/livewire/update', 'POST', [
        'components' => [['snapshot' => json_encode(['memo' => ['name' => 'app.filament.app.pages.change-password', 'path' => 'app/change-password']])]],
    ]);
    $changeForm->setUserResolver(fn () => $user);
    expect((new EnforcePasswordRotation)->handle($changeForm, fn () => response('ran'))->getContent())->toBe('ran');

    // The mandatory 2FA enrolment (forced first) is allowed by its page path.
    $mfaSetup = Request::create('/livewire/update', 'POST', [
        'components' => [['snapshot' => json_encode(['memo' => ['name' => 'filament.auth.multi-factor.set-up', 'path' => 'app/multi-factor-authentication/set-up']])]],
    ]);
    $mfaSetup->setUserResolver(fn () => $user);
    expect((new EnforcePasswordRotation)->handle($mfaSetup, fn () => response('ran'))->getContent())->toBe('ran');
});

it('clears the temporary-password flag when the user sets a new password', function () {
    $user = makeUser(UserRole::Recorder, ['must_change_password' => true]);
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    Livewire::test(ChangePassword::class)
        ->set('password', 'brandNew123')
        ->set('password_confirmation', 'brandNew123')
        ->call('save');

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('brandNew123', $user->password))->toBeTrue()
        ->and($user->last_login_at)->not->toBeNull();
});
