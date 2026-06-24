<?php

namespace App\Filament\Resources\AcademyUsers\Pages;

use App\Filament\Resources\AcademyUsers\AcademyUserResource;
use App\Filament\Resources\AcademyUsers\Tables\AcademyUsersTable;
use App\Models\AcademyUser;
use App\Support\TempPassword;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAcademyUsers extends ListRecords
{
    protected static string $resource = AcademyUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')
                ->label(__('academy.add'))
                ->icon('heroicon-o-user-plus')
                ->modalHeading(__('academy.add'))
                ->modalDescription(__('academy.add_desc'))
                ->modalSubmitActionLabel(__('academy.add'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('academy.f_name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label(__('academy.f_email'))
                        ->required()
                        ->email()
                        ->maxLength(255),
                    TextInput::make('password')
                        ->label(__('academy.f_password'))
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->maxLength(255)
                        ->helperText(__('academy.f_password_help')),
                ])
                ->action(function (array $data): void {
                    $email = mb_strtolower(trim((string) $data['email']));

                    if (AcademyUser::query()->where('email', $email)->exists()) {
                        Notification::make()->title(__('academy.email_taken'))->danger()->send();

                        return;
                    }

                    $password = filled($data['password'] ?? null)
                        ? (string) $data['password']
                        : TempPassword::generate();

                    AcademyUser::query()->create([
                        'name' => (string) $data['name'],
                        'email' => $email,
                        'password' => $password, // 'hashed' cast hashes it
                        'is_active' => true,
                    ]);

                    AcademyUsersTable::credentials((string) __('academy.added_title'), $email, $password);
                }),
        ];
    }
}
