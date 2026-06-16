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
                    TextInput::make('subdomain')
                        ->label(__('app.client.subdomain'))
                        ->required()
                        ->maxLength(40)
                        ->suffix('.'.config('niu.tenant_base_domain', 'localhost'))
                        ->helperText(__('app.client.subdomain_help'))
                        ->rule('regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/')
                        ->extraInputAttributes(['style' => 'text-transform:lowercase']),
                    TextInput::make('admin_name')
                        ->label(__('app.client.admin_name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('admin_email')
                        ->label(__('app.client.admin_email'))
                        ->required()
                        ->email()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    try {
                        $client = app(ClientProvisioningService::class)->provision(
                            name: $data['name'],
                            subdomain: $data['subdomain'],
                            adminName: $data['admin_name'],
                            adminEmail: $data['admin_email'],
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
                            .'<strong>'.e($client->domain).'/app</strong><br>'
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
