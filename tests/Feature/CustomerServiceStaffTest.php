<?php

declare(strict_types=1);

use App\Domain\Auth\StaffRole;
use App\Filament\Pages\ChangePassword;
use App\Filament\Resources\SupportStaff\SupportStaffResource;
use App\Http\Middleware\ForcePasswordChange;
use App\Mail\SupportStaffCredentialsMail;
use App\Models\User;
use App\Services\Support\SupportStaffService;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('onboards a central support agent with a temp password and emails credentials', function () {
    Mail::fake();

    $result = app(SupportStaffService::class)->onboard('Amah Tee', 'Amah@EA.test', StaffRole::Agent);
    $user = $result['user'];

    expect($user->isCentralUser())->toBeTrue()
        ->and($user->staff_role)->toBe(StaffRole::Agent)
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->email)->toBe('amah@ea.test') // normalised
        ->and($result['password'])->toBeString();

    Mail::assertSent(SupportStaffCredentialsMail::class);
});

it('rejects onboarding an email already in use', function () {
    User::query()->create(['name' => 'X', 'email' => 'dup@ea.test', 'password' => Hash::make('secret123'), 'staff_role' => StaffRole::Agent]);

    expect(fn () => app(SupportStaffService::class)->onboard('Y', 'dup@ea.test', StaffRole::Agent))
        ->toThrow(ValidationException::class);
});

it('lets owners manage staff but not agents', function () {
    $owner = User::query()->create(['name' => 'O', 'email' => 'o@ea.test', 'password' => Hash::make('secret123'), 'staff_role' => StaffRole::Owner]);
    $agent = User::query()->create(['name' => 'A', 'email' => 'a@ea.test', 'password' => Hash::make('secret123'), 'staff_role' => StaffRole::Agent]);

    expect($owner->canManageStaff())->toBeTrue()
        ->and($agent->canManageStaff())->toBeFalse();

    test()->actingAs($owner);
    expect(SupportStaffResource::canAccess())->toBeTrue();

    test()->actingAs($agent);
    expect(SupportStaffResource::canAccess())->toBeFalse();
});

it('forces a temp-password staff member to the admin change-password page', function () {
    $staff = User::query()->create([
        'name' => 'New', 'email' => 'new@ea.test', 'password' => Hash::make('secret123'),
        'staff_role' => StaffRole::Agent, 'must_change_password' => true,
    ]);
    Filament::setCurrentPanel('admin');

    $request = Request::create('/admin');
    $request->setUserResolver(fn () => $staff);

    $response = (new ForcePasswordChange)->handle($request, fn () => response('reached'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('change-password');
});

it('clears the temp-password flag when a staff member sets a new password on /admin', function () {
    $staff = User::query()->create([
        'name' => 'New', 'email' => 'set@ea.test', 'password' => Hash::make('secret123'),
        'staff_role' => StaffRole::Agent, 'must_change_password' => true,
    ]);
    test()->actingAs($staff);
    Filament::setCurrentPanel('admin');

    Livewire::test(ChangePassword::class)
        ->set('password', 'brandNew123')
        ->set('password_confirmation', 'brandNew123')
        ->call('save');

    expect($staff->fresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('brandNew123', $staff->fresh()->password))->toBeTrue();
});
