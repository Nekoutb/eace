<?php

declare(strict_types=1);

use App\Filament\Resources\AcademyUsers\Pages\ListAcademyUsers;
use App\Mail\AcademyCredentialsMail;
use App\Models\AcademyUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function academyUser(string $email, bool $active = true, bool $mustChange = false): AcademyUser
{
    return AcademyUser::query()->create([
        'name' => 'Rep '.$email,
        'email' => $email,
        'password' => 'secret-pass-1', // hashed by the model cast
        'is_active' => $active,
        'must_change_password' => $mustChange,
    ]);
}

it('redirects an unauthenticated visitor from the academy to the sign-in', function () {
    $this->get('/academy')->assertRedirect(route('academy.login'));
});

it('shows the academy sign-in page', function () {
    $this->get('/academy/login')->assertOk()->assertSee('Academy');
});

it('signs a staff member in with email + password and reaches the module (no two-factor)', function () {
    academyUser('rep@ea.test');

    $this->post('/academy/login', ['email' => 'rep@ea.test', 'password' => 'secret-pass-1'])
        ->assertRedirect(route('academy.index'));

    expect(auth('academy')->check())->toBeTrue();

    // Straight into the content — no 2FA enrolment/challenge in between.
    $this->get('/academy')->assertOk()->assertSee('ELITE');
});

it('rejects a wrong password', function () {
    academyUser('rep2@ea.test');

    $this->post('/academy/login', ['email' => 'rep2@ea.test', 'password' => 'nope'])
        ->assertSessionHasErrors('email');

    expect(auth('academy')->check())->toBeFalse();
});

it('refuses a de-activated account', function () {
    academyUser('gone@ea.test', active: false);

    $this->post('/academy/login', ['email' => 'gone@ea.test', 'password' => 'secret-pass-1'])
        ->assertSessionHasErrors('email');

    expect(auth('academy')->check())->toBeFalse();
});

it('gates the module assets behind the academy guard', function () {
    $this->get('/academy/assets/screens/app-login.png')->assertRedirect(route('academy.login'));

    $this->actingAs(academyUser('r4@ea.test'), 'academy')
        ->get('/academy/assets/screens/app-login.png')->assertOk();
});

it('returns 404 for a missing asset', function () {
    $this->actingAs(academyUser('r5@ea.test'), 'academy')
        ->get('/academy/assets/screens/does-not-exist.png')->assertNotFound();
});

it('emails credentials and flags a password change when an operator onboards a member', function () {
    Mail::fake();
    $admin = User::query()->create(['name' => 'Op', 'email' => 'op@ea.test', 'password' => Hash::make('secret123')]);
    test()->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(ListAcademyUsers::class)
        ->callAction('add', ['name' => 'New Rep', 'email' => 'NewRep@EA.test', 'password' => 'staffpass1']);

    $created = AcademyUser::query()->where('email', 'newrep@ea.test')->first();

    expect($created)->not->toBeNull()
        ->and($created->is_active)->toBeTrue()
        ->and($created->must_change_password)->toBeTrue()   // prompted to change on first sign-in
        ->and(auth('academy')->attempt(['email' => 'newrep@ea.test', 'password' => 'staffpass1', 'is_active' => true]))->toBeTrue();

    Mail::assertSent(AcademyCredentialsMail::class, fn (AcademyCredentialsMail $m): bool => $m->email === 'newrep@ea.test');
});

it('forces a freshly onboarded member to set their own password before the module', function () {
    $u = academyUser('fresh@ea.test', mustChange: true);
    $this->actingAs($u, 'academy');

    // While a one-time password is pending, the module redirects to the change page.
    $this->get('/academy')->assertRedirect(route('academy.password'));
    $this->get(route('academy.password'))->assertOk()->assertSee('Academy');

    // Setting a new password clears the flag and lets them in.
    $this->post(route('academy.password.update'), ['password' => 'my-new-pass-9', 'password_confirmation' => 'my-new-pass-9'])
        ->assertRedirect(route('academy.index'));

    expect($u->fresh()->must_change_password)->toBeFalse();
    $this->get('/academy')->assertOk()->assertSee('ELITE');
});
