<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Services\Tenant\ClientProvisioningException;
use App\Services\Tenant\ClientProvisioningService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('provision')
                ->label(__('app.client.provision'))
                ->icon('heroicon-o-plus-circle')
                ->modalHeading(__('app.client.provision'))
                ->modalDescription(__('app.client.provision_desc'))
                ->modalSubmitActionLabel(__('app.client.provision'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('app.client.org_name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('admin_name')
                        ->label(__('app.client.admin_name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('admin_email')
                        ->label(__('app.client.admin_email'))
                        ->required()
                        ->email()
                        ->maxLength(255),
                    TextInput::make('allocated_space_mb')
                        ->label(__('app.client.allocated_space'))
                        ->helperText(__('app.client.allocated_space_help'))
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->suffix('MB')
                        ->default((int) config('niu.default_allocated_space_mb', 1024)),
                ])
                ->action(function (array $data): void {
                    try {
                        $client = app(ClientProvisioningService::class)->provision(
                            name: $data['name'],
                            adminName: $data['admin_name'],
                            adminEmail: $data['admin_email'],
                            allocatedSpaceMb: (int) $data['allocated_space_mb'],
                        );
                    } catch (ClientProvisioningException $e) {
                        Notification::make()
                            ->title(__('app.client.provision_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title(__('app.client.provision_failed'))
                            ->body(__('app.client.provision_error'))
                            ->danger()
                            ->send();

                        return;
                    }

                    // The temporary password is shown here once and never stored
                    // in plain text — keep the toast until the admin dismisses it.
                    Notification::make()
                        ->title(__('app.client.provisioned'))
                        ->body(new HtmlString(
                            '<div style="line-height:1.7">'
                            .e(__('app.client.cred_login_at')).' <strong>'.e($client->loginUrl).'</strong><br>'
                            .e(__('app.client.cred_email')).': <code>'.e($client->adminEmail).'</code><br>'
                            .e(__('app.client.cred_password')).': <code>'.e($client->temporaryPassword).'</code><br>'
                            .'<em>'.e(__('app.client.cred_once')).'</em></div>'
                        ))
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
