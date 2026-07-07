<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Auth\AppAuthentication;
use App\Models\DataAccessLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Evidence\EvidenceStorageService;
use App\Services\Tenant\ClientPasswordReset;
use App\Support\Impersonation;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

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
                    ->getStateUsing(fn (Tenant $record): string => (string) ($record->name ?? $record->id))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('data->name', $direction)),
                TextColumn::make('id')
                    ->label(__('app.client.identifier'))
                    ->fontFamily(FontFamily::Mono)
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('firm_id')
                    ->label(__('app.client.firm'))
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (Tenant $record): ?string => $record->firm()->first()?->name)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('app.ui.status'))
                    ->badge()
                    ->getStateUsing(fn (Tenant $record): string => $record->isSuspended()
                        ? (string) __('app.client.suspended')
                        : (string) __('app.client.active'))
                    ->color(fn (Tenant $record): string => $record->isSuspended() ? 'danger' : 'success'),
                TextColumn::make('allocated_space_mb')
                    ->label(__('app.client.quota'))
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (Tenant $record): string => number_format($record->allocatedSpaceMb()).' MB')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('data->allocated_space_mb', $direction)),
                TextColumn::make('created_at')
                    ->label(__('app.client.created'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->emptyStateHeading(__('app.client.empty_heading'))
            ->emptyStateDescription(__('app.client.empty_desc'))
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->recordActions([
                ActionGroup::make([
                    self::badgeInAction(),
                    self::renameAction(),
                    self::setQuotaAction(),
                    self::resetPasswordAction(),
                    self::suspendAction(),
                    self::reactivateAction(),
                    DeleteAction::make()
                        ->modalHeading(__('app.client.delete'))
                        ->modalDescription(__('app.client.delete_warning')),
                ])->label(__('app.client.actions')),
            ]);
    }

    /**
     * Badge in (read-only): after re-verifying the admin's own TOTP, stash a short-
     * lived grant and hand off to the impersonation enter-route, which performs the
     * auth/tenancy swap in a clean request. Only shown to admins who may impersonate.
     */
    private static function badgeInAction(): Action
    {
        return Action::make('badgeIn')
            ->label(__('app.client.badge_in'))
            ->icon('heroicon-o-eye')
            ->color('warning')
            ->visible(fn (): bool => Impersonation::canStart(auth()->user()))
            ->modalHeading(fn (Tenant $record): string => (string) __('app.client.badge_in_heading', ['client' => $record->name ?? $record->id]))
            ->modalDescription(__('app.client.badge_in_desc'))
            ->modalSubmitActionLabel(__('app.client.badge_in_submit'))
            ->schema([
                OneTimeCodeInput::make('code')
                    ->label(__('app.client.badge_in_code'))
                    ->required(),
            ])
            ->action(function (Tenant $record, array $data, Component $livewire): void {
                $user = auth()->user();
                $secret = $user instanceof User ? $user->getAppAuthenticationSecret() : null;

                if ($secret === null
                    || ! AppAuthentication::make()->verifyCode((string) ($data['code'] ?? ''), $secret, shouldPreventCodeReuse: true)) {
                    Notification::make()->title(__('app.client.badge_in_bad_code'))->danger()->send();

                    return;
                }

                session()->put('impersonation_grant', [
                    'tenant' => $record->getTenantKey(),
                    'admin' => auth()->id(),
                    'expires' => now()->addMinutes(2)->timestamp,
                ]);

                $livewire->redirect(route('impersonation.enter'), navigate: false);
            });
    }

    /**
     * "The client forgot their password": pick which of the workspace's users to
     * reset — a one-time password is generated, emailed to them, and shown ONCE
     * here so the operator can also pass it on directly. Firm members are reset
     * centrally (all their workspaces at once).
     */
    private static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label(__('app.client.reset_password'))
            ->icon('heroicon-o-key')
            ->color('gray')
            ->modalHeading(__('app.client.reset_password'))
            ->modalDescription(__('app.client.reset_password_desc'))
            ->schema([
                Select::make('email')
                    ->label(__('app.client.reset_password_user'))
                    ->options(fn (Tenant $record): array => app(ClientPasswordReset::class)->resettableUsers($record))
                    ->required()
                    ->native(false),
            ])
            ->action(function (Tenant $record, array $data): void {
                $temporary = app(ClientPasswordReset::class)->reset($record, (string) $data['email']);

                Notification::make()
                    ->title(__('app.client.reset_password_done'))
                    ->body(__('app.client.reset_password_body', ['email' => (string) $data['email'], 'password' => $temporary]))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    /** Suspend the workspace (e.g. unpaid bills): sign-in + all use blocked, data untouched. */
    private static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label(__('app.client.suspend'))
            ->icon('heroicon-o-pause-circle')
            ->color('danger')
            ->visible(fn (Tenant $record): bool => $record->suspended_at === null)
            ->requiresConfirmation()
            ->modalHeading(fn (Tenant $record): string => (string) __('app.client.suspend_heading', ['client' => $record->name ?? $record->id]))
            ->modalDescription(__('app.client.suspend_desc'))
            ->action(function (Tenant $record): void {
                $record->suspend();
                DataAccessLog::record('client_suspend', 'tenant:'.$record->getTenantKey(), 'by admin '.(string) auth()->user()?->getAttribute('email'));

                Notification::make()->title(__('app.client.suspended_done'))->warning()->send();
            });
    }

    private static function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->label(__('app.client.reactivate'))
            ->icon('heroicon-o-play-circle')
            ->color('success')
            ->visible(fn (Tenant $record): bool => $record->suspended_at !== null)
            ->requiresConfirmation()
            ->action(function (Tenant $record): void {
                $record->unsuspend();
                DataAccessLog::record('client_reactivate', 'tenant:'.$record->getTenantKey(), 'by admin '.(string) auth()->user()?->getAttribute('email'));

                Notification::make()->title(__('app.client.reactivated_done'))->success()->send();
            });
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

    /**
     * Set a workspace's evidence storage quota to any value. The quota lives in the
     * stancl `data` column (written through by update), so it takes effect at once —
     * the dashboard gauge and the Clients column both read allocatedSpaceMb().
     */
    private static function setQuotaAction(): Action
    {
        return Action::make('setQuota')
            ->label(__('app.client.set_quota'))
            ->icon('heroicon-o-circle-stack')
            ->color('gray')
            ->modalHeading(__('app.client.set_quota'))
            ->modalDescription(function (Tenant $record): string {
                try {
                    $used = (float) $record->run(fn (): float => app(EvidenceStorageService::class)->usedMb());

                    return (string) __('app.client.quota_usage', [
                        'used' => number_format($used, 1),
                        'quota' => number_format($record->allocatedSpaceMb()),
                    ]);
                } catch (\Throwable) {
                    return (string) __('app.client.quota_set_desc');
                }
            })
            ->fillForm(fn (Tenant $record): array => ['allocated_space_mb' => $record->allocatedSpaceMb()])
            ->schema([
                TextInput::make('allocated_space_mb')
                    ->label(__('app.client.allocated_space'))
                    ->helperText(__('app.client.allocated_space_help'))
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->suffix('MB'),
            ])
            ->action(function (Tenant $record, array $data): void {
                $record->update(['allocated_space_mb' => (int) $data['allocated_space_mb']]);

                Notification::make()
                    ->title(__('app.client.quota_updated'))
                    ->success()
                    ->send();
            });
    }
}
