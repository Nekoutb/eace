<?php

namespace App\Filament\Resources\SupportStaff\Pages;

use App\Domain\Auth\StaffRole;
use App\Filament\Resources\SupportStaff\SupportStaffResource;
use App\Services\Support\SupportStaffService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSupportStaff extends ListRecords
{
    protected static string $resource = SupportStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('onboard')
                ->label(__('support.staff.onboard'))
                ->icon('heroicon-o-user-plus')
                ->modalHeading(__('support.staff.onboard'))
                ->modalSubmitActionLabel(__('support.staff.onboard'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('support.staff.name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label(__('support.staff.email'))
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Select::make('staff_role')
                        ->label(__('support.staff.role'))
                        ->options(StaffRole::assignableOptions())
                        ->default(StaffRole::Agent->value)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $result = app(SupportStaffService::class)->onboard(
                        (string) $data['name'],
                        (string) $data['email'],
                        StaffRole::from((string) $data['staff_role']),
                    );

                    Notification::make()
                        ->title(__('support.staff.created', ['email' => $result['user']->email]))
                        ->body(__('support.staff.temp_password').': '.$result['password'])
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
