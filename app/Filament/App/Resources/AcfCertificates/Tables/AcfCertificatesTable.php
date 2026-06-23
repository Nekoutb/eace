<?php

namespace App\Filament\App\Resources\AcfCertificates\Tables;

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfStatus;
use App\Models\AcfCertificate;
use App\Models\User;
use App\Services\Acf\AcfAuthenticationService;
use App\Services\Acf\AcfReminderService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

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
                    ->sortable()
                    ->searchable(),
                TextColumn::make('niu')
                    ->label(__('app.ui.niu'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->copyable()
                    ->copyMessage(__('app.ui.copied_niu'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('taxpayer_name')
                    ->label(__('app.ui.taxpayer'))
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('app.ui.validity'))
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (AcfStatus $state): string => $state->label())
                    ->color(fn (AcfStatus $state): string => $state->color())
                    ->icon(fn (AcfStatus $state): string => $state->icon()),
                TextColumn::make('authenticity_status')
                    ->label(__('app.ui.authenticity'))
                    ->badge()
                    ->placeholder(__('app.acf_auth.unverified'))
                    ->sortable()
                    ->formatStateUsing(fn (AcfAuthenticityStatus $state): string => $state->label())
                    ->color(fn (AcfAuthenticityStatus $state): string => $state->color()),
                TextColumn::make('issued_at')
                    ->label(__('app.ui.issued_at'))
                    ->date('d/m/Y')
                    ->sortable(),
                ViewColumn::make('expires_at')
                    ->label(__('app.acf_reminder.col_label'))
                    ->view('filament.app.tables.columns.acf-expiry')
                    ->sortable()
                    ->tooltip(fn (AcfCertificate $record): ?string => $record->reminded_at !== null
                        ? __('app.acf_reminder.reminded_on', ['date' => $record->reminded_at->format('d/m/Y')])
                        : null),
            ])
            ->filters([
                Filter::make('expiring_soon')
                    ->label(__('app.acf_reminder.filter_expiring', ['days' => self::orangeDays()]))
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('expires_at', '>=', now()->toDateString())
                        ->whereDate('expires_at', '<=', now()->addDays(self::orangeDays())->toDateString())),
                Filter::make('expired')
                    ->label(__('app.acf_reminder.filter_expired'))
                    ->query(fn (Builder $query): Builder => $query->whereDate('expires_at', '<', now()->toDateString())),
            ])
            ->recordActions([
                ViewAction::make(),
                self::authenticateAction(),
                self::remindAction(),
            ]);
    }

    private static function orangeDays(): int
    {
        return (int) config('niu.acf_reminder.orange_days', 20);
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

                // Tell the user when the verdict was reused from the shared cache
                // (the same NIU + reference was already authenticated elsewhere).
                if ($result->wasReused()) {
                    $notification->body(__('app.ui.authenticate_reused', ['date' => $result->reusedAt->translatedFormat('d/m/Y')]));
                }

                $notification->send();
            });
    }

    /**
     * Email the taxpayer that their certificate is about to expire. The message
     * leaves from the platform mailbox (so it delivers) but carries the acting
     * user's name + Reply-To, so the taxpayer's reply reaches that user. The
     * recipient is pre-filled from the linked counterparty and stays editable.
     */
    private static function remindAction(): Action
    {
        return Action::make('remindTaxpayer')
            ->label(__('app.acf_reminder.remind'))
            ->icon('heroicon-o-envelope')
            ->color('warning')
            ->modalHeading(__('app.acf_reminder.remind_heading'))
            ->modalDescription(fn (AcfCertificate $record): string => __('app.acf_reminder.remind_desc')
                .' (ACF '.$record->reference.' · '.__('app.ui.expires_at').' '.$record->expires_at->format('d/m/Y').')')
            ->modalSubmitActionLabel(__('app.acf_reminder.send'))
            ->fillForm(fn (AcfCertificate $record): array => ['email' => $record->thirdParty?->email])
            ->schema([
                TextInput::make('email')
                    ->label(__('app.acf_reminder.email_label'))
                    ->helperText(__('app.acf_reminder.email_help'))
                    ->email()
                    ->required()
                    ->maxLength(255),
                Textarea::make('note')
                    ->label(__('app.acf_reminder.note_label'))
                    ->placeholder(__('app.acf_reminder.note_placeholder'))
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (AcfCertificate $record, array $data): void {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return;
                }

                try {
                    app(AcfReminderService::class)->send($record, (string) $data['email'], $data['note'] ?? null, $user);
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title(__('app.acf_reminder.failed'))->danger()->send();

                    return;
                }

                Notification::make()->title(__('app.acf_reminder.sent', ['email' => $data['email']]))->success()->send();
            });
    }
}
