<?php

namespace App\Filament\App\Resources\Users\Pages;

use App\Domain\Auth\UserRole;
use App\Filament\App\Resources\Users\UserResource;
use App\Models\User;
use App\Support\TempPassword;
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
                        ->unique(table: User::class, column: 'email'),
                    Select::make('role')
                        ->label(__('app.user.role'))
                        ->required()
                        ->options(UserRole::options())
                        ->default(UserRole::Operator->value)
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $temporaryPassword = TempPassword::generate();

                    User::query()->create([
                        'name' => trim($data['name']),
                        'email' => trim($data['email']),
                        'password' => Hash::make($temporaryPassword),
                        'role' => $data['role'],
                        'must_change_password' => true,
                    ]);

                    UserResource::credentialsNotification(
                        (string) __('app.user.created_title'),
                        trim($data['email']),
                        $temporaryPassword,
                    );
                }),
        ];
    }
}
