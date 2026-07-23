<?php

namespace App\Filament\App\Resources\Users\Pages;

use App\Domain\Auth\UserRole;
use App\Filament\App\Resources\Users\UserResource;
use App\Models\TenantLogin;
use App\Models\User;
use App\Support\TempPassword;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Hash;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addUser')
                ->label(__('app.user.add'))
                ->icon('heroicon-o-user-plus')
                ->modalHeading(__('app.user.add'))
                ->modalDescription(__('app.user.add_desc'))
                ->modalSubmitActionLabel(__('app.user.add'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('app.user.name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label(__('app.user.email'))
                        ->required()
                        ->email()
                        ->maxLength(255)
                        ->unique(table: User::class, column: 'email')
                        // One sign-in email belongs to exactly one workspace: reject
                        // an email already registered to another client (central
                        // login directory), not just one taken inside this tenant.
                        ->rule(static function (): Closure {
                            return static function (string $attribute, mixed $value, Closure $fail): void {
                                $email = is_string($value) ? trim($value) : '';

                                if ($email !== '' && TenantLogin::isClaimedByAnotherWorkspace($email, (string) tenant()->getTenantKey())) {
                                    $fail((string) __('app.user.email_taken_other_workspace'));
                                }
                            };
                        }),
                    // Optional contact detail — never used to sign in, only so the
                    // organisation (and the operator) can reach the person.
                    TextInput::make('phone')
                        ->label(__('app.user.phone'))
                        ->helperText(__('app.user.phone_help'))
                        ->tel()
                        ->maxLength(30),
                    Select::make('role')
                        ->label(__('app.user.role'))
                        ->required()
                        ->options(UserRole::options())
                        ->default(UserRole::Recorder->value)
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $temporaryPassword = TempPassword::generate();
                    $phone = trim((string) ($data['phone'] ?? ''));

                    User::query()->create([
                        'name' => trim($data['name']),
                        'email' => trim($data['email']),
                        'phone' => $phone !== '' ? $phone : null,
                        'password' => Hash::make($temporaryPassword),
                        'role' => $data['role'],
                        'must_change_password' => true,
                    ]);

                    // The new user gets their own onboarding email (credentials +
                    // sign-in button); the toast stays as the admin's backup copy.
                    UserResource::emailCredentials(trim($data['name']), trim($data['email']), $temporaryPassword);

                    UserResource::credentialsNotification(
                        (string) __('app.user.created_title'),
                        trim($data['email']),
                        $temporaryPassword,
                    );
                }),
        ];
    }
}
