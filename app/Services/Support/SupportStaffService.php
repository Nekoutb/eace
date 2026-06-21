<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Domain\Auth\StaffRole;
use App\Mail\SupportStaffCredentialsMail;
use App\Models\TenantLogin;
use App\Models\User;
use App\Support\TempPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Onboards central customer-service staff. Mirrors the workspace-admin onboarding:
 * create the account with a one-time password, force a change on first sign-in,
 * email the credentials, and let mandatory 2FA enrol on first login. The operator
 * (owner) does this from the admin panel. Runs in the central context — no tenant.
 */
final class SupportStaffService
{
    /**
     * @return array{user: User, password: string}
     */
    public function onboard(string $name, string $email, StaffRole $role, ?string $telegramUsername = null): array
    {
        $email = mb_strtolower(trim($email));

        if (User::query()->where('email', $email)->exists() || TenantLogin::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => (string) __('support.staff.email_taken')]);
        }

        $password = TempPassword::generate();

        $user = User::query()->create([
            'name' => trim($name),
            'email' => $email,
            'password' => Hash::make($password),
            'staff_role' => $role,
            'telegram_username' => User::normaliseTelegramUsername($telegramUsername),
            'must_change_password' => true,
        ]);

        Mail::to($email)->locale(app()->getLocale())->send(
            new SupportStaffCredentialsMail($user->name, $email, $password, url('/admin')),
        );

        return ['user' => $user, 'password' => $password];
    }
}
