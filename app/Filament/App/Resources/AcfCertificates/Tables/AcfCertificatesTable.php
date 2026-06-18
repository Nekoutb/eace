<?php

namespace App\Filament\App\Resources\AcfCertificates\Tables;

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfStatus;
use App\Models\AcfCertificate;
use App\Services\Acf\AcfAuthenticationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AcfCertificatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expires_at', 'asc')
            ->columns([
                TextColumn::make('reference')
                    ->label(__('app.ui.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('niu')
                    ->label(__('app.ui.niu'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->copyable()
                    ->copyMessage(__('app.ui.copied_niu'))
                    ->searchable(),
                TextColumn::make('taxpayer_name')
                    ->label(__('app.ui.taxpayer'))
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('app.ui.validity'))
                    ->badge()
                    ->formatStateUsing(fn (AcfStatus $state): string => $state->label())
                    ->color(fn (AcfStatus $state): string => $state->color())
                    ->icon(fn (AcfStatus $state): string => $state->icon()),
                TextColumn::make('authenticity_status')
                    ->label(__('app.ui.authenticity'))
                    ->badge()
                    ->placeholder(__('app.acf_auth.unverified'))
                    ->formatStateUsing(fn (AcfAuthenticityStatus $state): string => $state->label())
                    ->color(fn (AcfAuthenticityStatus $state): string => $state->color()),
                TextColumn::make('issued_at')
                    ->label(__('app.ui.issued_at'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('app.ui.expires_at'))
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                self::authenticateAction(),
            ]);
    }

    /**
     * Live Harmony2 authenticity check (CGI art. L 94 bis al. 3). Drives the DGI
     * portal headlessly, stores the verdict screenshot, and reports the outcome.
     */
    private static function authenticateAction(): Action
    {
        return Action::make('authenticate')
            ->label(__('app.ui.authenticate'))
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('app.ui.authenticate'))
            ->modalDescription(__('app.ui.authenticate_confirm'))
            ->action(function (AcfCertificate $record): void {
                $result = app(AcfAuthenticationService::class)->authenticate($record);

                $notification = Notification::make()->title($result->status->label());

                match ($result->status) {
                    AcfAuthenticityStatus::Authentic => $notification->success(),
                    AcfAuthenticityStatus::NotAuthentic => $notification->danger(),
                    AcfAuthenticityStatus::Unverified => $notification->warning()
                        ->body(__('app.ui.authenticate_unverified')),
                };

                $notification->send();
            });
    }
}
