<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.client.org_name'))
                    ->weight('bold')
                    ->getStateUsing(fn (Tenant $record): string => (string) ($record->name ?? $record->id)),
                TextColumn::make('domain')
                    ->label(__('app.client.subdomain'))
                    ->fontFamily(FontFamily::Mono)
                    ->getStateUsing(fn (Tenant $record): ?string => $record->primaryDomain())
                    ->copyable(),
                TextColumn::make('id')
                    ->label(__('app.client.identifier'))
                    ->fontFamily(FontFamily::Mono)
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('app.client.created'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                self::openAction(),
                self::renameAction(),
                DeleteAction::make()
                    ->modalHeading(__('app.client.delete'))
                    ->modalDescription(__('app.client.delete_warning')),
            ]);
    }

    /** Open the client's tenant app in a new tab (honours the current scheme/port). */
    private static function openAction(): Action
    {
        return Action::make('open')
            ->label(__('app.client.open'))
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('gray')
            ->url(function (Tenant $record): ?string {
                $domain = $record->primaryDomain();
                if ($domain === null) {
                    return null;
                }
                $port = request()->getPort();
                $portPart = in_array($port, [80, 443, null], true) ? '' : ':'.$port;

                return request()->getScheme().'://'.$domain.$portPart.'/app';
            })
            ->openUrlInNewTab();
    }

    private static function renameAction(): Action
    {
        return Action::make('rename')
            ->label(__('app.client.rename'))
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->fillForm(fn (Tenant $record): array => ['name' => $record->name])
            ->schema([
                TextInput::make('name')
                    ->label(__('app.client.org_name'))
                    ->required()
                    ->maxLength(255),
            ])
            ->action(fn (Tenant $record, array $data) => $record->update(['name' => $data['name']]));
    }
}
