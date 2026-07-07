<?php

namespace App\Filament\App\Pages\FirmConsole;

use App\Domain\Auth\UserRole;
use App\Domain\Firm\FirmRole;
use App\Mail\WorkspaceCredentialsMail;
use App\Models\AccountAssignment;
use App\Models\FirmMember;
use App\Models\Tenant;
use App\Services\Firm\FirmException;
use App\Services\Firm\FirmMembershipService;
use App\Support\AccountSwitcher;
use App\Support\TempPassword;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Mail;

/**
 * The firm's team: invite members (credentials emailed + shown once), give or
 * revoke access to client sub-accounts with a per-account role, reset a member's
 * password. Scoped to the signed-in power user's firm; every action re-verifies
 * the record belongs to that firm. Rendered only by the FirmConsole page.
 */
class FirmTeamWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return AccountSwitcher::managedFirm(Filament::auth()->user()) !== null;
    }

    public function table(Table $table): Table
    {
        $firm = AccountSwitcher::managedFirm(Filament::auth()->user());
        $firmId = $firm?->getKey() ?? -1;

        return $table
            ->heading((string) __('firm.console.team_heading'))
            ->description((string) __('firm.console.team_desc'))
            ->query(FirmMember::query()->where('firm_id', $firmId))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label(__('firm.console.member_name'))->weight('bold'),
                TextColumn::make('email')->label(__('app.ui.email_label')),
                TextColumn::make('role')
                    ->label(__('firm.console.member_role'))
                    ->badge()
                    ->getStateUsing(fn (FirmMember $record): string => $record->role->label())
                    ->color(fn (FirmMember $record): string => $record->isPowerUser() ? 'success' : 'gray'),
                TextColumn::make('accounts')
                    ->label(__('firm.console.member_accounts'))
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (FirmMember $record): int => AccountAssignment::query()->where('firm_member_id', $record->getKey())->count()),
            ])
            ->emptyStateHeading(__('firm.console.team_empty'))
            ->emptyStateIcon('heroicon-o-user-group')
            ->headerActions([
                Action::make('addMember')
                    ->label(__('firm.console.add_member'))
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        TextInput::make('name')->label(__('firm.console.member_name'))->required()->maxLength(255),
                        TextInput::make('email')->label(__('app.ui.email_label'))->email()->required()->maxLength(255),
                        Select::make('role')
                            ->label(__('firm.console.member_role'))
                            ->options([
                                FirmRole::Member->value => FirmRole::Member->label(),
                                FirmRole::PowerUser->value => FirmRole::PowerUser->label(),
                            ])
                            ->default(FirmRole::Member->value)
                            ->required()
                            ->native(false),
                        // A member can only sign in through an assigned account —
                        // so the invite REQUIRES at least one, or the welcome email
                        // would lead to "email not recognised".
                        Select::make('accounts')
                            ->label(__('firm.console.invite_accounts'))
                            ->helperText(__('firm.console.invite_accounts_help'))
                            ->multiple()
                            ->options(function (): array {
                                $firm = AccountSwitcher::managedFirm(Filament::auth()->user());
                                $out = [];
                                foreach (Tenant::query()->where('firm_id', $firm?->getKey() ?? -1)->cursor() as $t) {
                                    $out[(string) $t->getTenantKey()] = (string) ($t->name ?? $t->getTenantKey());
                                }

                                return $out;
                            })
                            ->required()
                            ->native(false),
                        Select::make('account_role')
                            ->label(__('firm.console.account_role'))
                            ->options(UserRole::options())
                            ->default(UserRole::Recorder->value)
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data): void {
                        $firm = AccountSwitcher::managedFirm(Filament::auth()->user());
                        $actor = AccountSwitcher::memberFor(Filament::auth()->user());
                        if ($firm === null) {
                            return;
                        }

                        try {
                            $added = app(FirmMembershipService::class)->addMember(
                                $firm,
                                (string) $data['name'],
                                (string) $data['email'],
                                FirmRole::from((string) $data['role']),
                            );

                            // Give access BEFORE the email goes out, so its sign-in
                            // button works the moment it lands in their inbox.
                            foreach ((array) $data['accounts'] as $tenantId) {
                                app(FirmMembershipService::class)->assign(
                                    $added->member,
                                    (string) $tenantId,
                                    UserRole::from((string) $data['account_role']),
                                    $actor !== null ? (int) $actor->getKey() : null,
                                );
                            }
                        } catch (FirmException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        // Credentials by email (queued — never stalls the modal) +
                        // shown once here as the power user's backup copy.
                        try {
                            Mail::to($added->member->email)->locale(app()->getLocale())->queue(new WorkspaceCredentialsMail(
                                name: $added->member->name,
                                orgName: (string) $firm->name,
                                email: $added->member->email,
                                temporaryPassword: (string) $added->temporaryPassword,
                                loginUrl: rtrim((string) config('app.url'), '/').'/app/login',
                            ));
                        } catch (\Throwable $e) {
                            report($e);
                        }

                        Notification::make()
                            ->title(__('firm.console.member_added'))
                            ->body(__('firm.console.member_added_body', ['email' => $added->member->email, 'password' => (string) $added->temporaryPassword]))
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::giveAccessAction(),
                    self::revokeAccessAction(),
                    self::resetPasswordAction(),
                ])->label(__('app.client.actions')),
            ]);
    }

    private static function giveAccessAction(): Action
    {
        return Action::make('giveAccess')
            ->label(__('firm.console.give_access'))
            ->icon('heroicon-o-key')
            ->color('gray')
            ->schema([
                Select::make('tenant')
                    ->label(__('firm.console.account'))
                    ->options(function (FirmMember $record): array {
                        $firm = AccountSwitcher::managedFirm(Filament::auth()->user());
                        if ($firm === null || (int) $record->firm_id !== (int) $firm->getKey()) {
                            return [];
                        }
                        $already = AccountAssignment::query()->where('firm_member_id', $record->getKey())->pluck('tenant_id')->all();
                        $out = [];
                        foreach (Tenant::query()->where('firm_id', $firm->getKey())->whereNotIn('id', $already)->cursor() as $t) {
                            $out[(string) $t->getTenantKey()] = (string) ($t->name ?? $t->getTenantKey());
                        }

                        return $out;
                    })
                    ->required()
                    ->native(false),
                Select::make('role')
                    ->label(__('firm.console.account_role'))
                    ->options(UserRole::options())
                    ->default(UserRole::Admin->value)
                    ->required()
                    ->native(false),
            ])
            ->action(function (FirmMember $record, array $data): void {
                $firm = AccountSwitcher::managedFirm(Filament::auth()->user());
                $actor = AccountSwitcher::memberFor(Filament::auth()->user());
                if ($firm === null || (int) $record->firm_id !== (int) $firm->getKey()) {
                    return;
                }

                try {
                    app(FirmMembershipService::class)->assign(
                        $record,
                        (string) $data['tenant'],
                        UserRole::from((string) $data['role']),
                        $actor !== null ? (int) $actor->getKey() : null,
                    );
                } catch (FirmException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('firm.console.access_given'))->success()->send();
            });
    }

    private static function revokeAccessAction(): Action
    {
        return Action::make('revokeAccess')
            ->label(__('firm.console.revoke_access'))
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->schema([
                Select::make('tenant')
                    ->label(__('firm.console.account'))
                    ->options(function (FirmMember $record): array {
                        $ids = AccountAssignment::query()->where('firm_member_id', $record->getKey())->pluck('tenant_id')->all();
                        $out = [];
                        foreach (Tenant::query()->whereIn('id', $ids)->cursor() as $t) {
                            $out[(string) $t->getTenantKey()] = (string) ($t->name ?? $t->getTenantKey());
                        }

                        return $out;
                    })
                    ->required()
                    ->native(false),
            ])
            ->action(function (FirmMember $record, array $data): void {
                $firm = AccountSwitcher::managedFirm(Filament::auth()->user());
                if ($firm === null || (int) $record->firm_id !== (int) $firm->getKey()) {
                    return;
                }

                app(FirmMembershipService::class)->unassign($record, (string) $data['tenant']);

                Notification::make()->title(__('firm.console.access_revoked'))->success()->send();
            });
    }

    private static function resetPasswordAction(): Action
    {
        return Action::make('resetMemberPassword')
            ->label(__('app.client.reset_password'))
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (FirmMember $record): void {
                $firm = AccountSwitcher::managedFirm(Filament::auth()->user());
                if ($firm === null || (int) $record->firm_id !== (int) $firm->getKey()) {
                    return;
                }

                $temporary = TempPassword::generate();
                $record->forceFill(['password' => $temporary, 'must_change_password' => true])->save();
                app(FirmMembershipService::class)->syncCredentials($record);

                try {
                    Mail::to($record->email)->locale(app()->getLocale())->queue(new WorkspaceCredentialsMail(
                        name: $record->name,
                        orgName: (string) $firm->name,
                        email: $record->email,
                        temporaryPassword: $temporary,
                        loginUrl: rtrim((string) config('app.url'), '/').'/app/login',
                    ));
                } catch (\Throwable $e) {
                    report($e);
                }

                Notification::make()
                    ->title(__('app.client.reset_password_done'))
                    ->body(__('app.client.reset_password_body', ['email' => $record->email, 'password' => $temporary]))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
