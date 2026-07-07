<?php

namespace App\Filament\App\Pages\FirmConsole;

use App\Domain\Auth\UserRole;
use App\Models\AccountAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Firm\FirmException;
use App\Services\Firm\FirmMembershipService;
use App\Support\AccountSwitcher;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The firm's client sub-accounts: each is an isolated workspace carrying that
 * client's own NIU, certificates and evidence. Creating one auto-assigns the
 * creating power user (so it appears in their switcher at once). Scoped to the
 * signed-in power user's firm — never trusts the request. Rendered only by the
 * FirmConsole page (not dashboard-discovered).
 */
class FirmSubAccountsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return AccountSwitcher::managedFirm(Filament::auth()->user()) !== null;
    }

    public function table(Table $table): Table
    {
        $firm = AccountSwitcher::managedFirm(Filament::auth()->user());
        $firmId = $firm !== null ? $firm->getKey() : -1;

        return $table
            ->heading((string) __('firm.console.accounts_heading', [
                'count' => $firm !== null ? $firm->subAccountCount() : 0,
                'cap' => $firm !== null ? $firm->max_sub_accounts : 0,
            ]))
            ->description((string) __('firm.console.accounts_desc'))
            ->query(Tenant::query()->where('firm_id', $firmId))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label(__('firm.console.account_name'))
                    ->weight('bold')
                    ->getStateUsing(fn (Tenant $record): string => (string) ($record->name ?? $record->id)),
                TextColumn::make('id')
                    ->label(__('app.client.identifier'))
                    ->fontFamily(FontFamily::Mono)
                    ->badge()
                    ->color('gray'),
                TextColumn::make('team')
                    ->label(__('firm.console.account_team'))
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (Tenant $record): int => AccountAssignment::query()->where('tenant_id', $record->id)->count()),
                TextColumn::make('created_at')
                    ->label(__('app.client.created'))
                    ->dateTime('d/m/Y'),
            ])
            ->emptyStateHeading(__('firm.console.accounts_empty'))
            ->emptyStateDescription(__('firm.console.accounts_empty_desc'))
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->headerActions([
                Action::make('createSubAccount')
                    ->label(__('firm.console.create_account'))
                    ->icon('heroicon-o-plus')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('firm.console.account_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('niu')
                            ->label(__('firm.console.account_niu'))
                            ->helperText(__('firm.console.account_niu_help'))
                            ->maxLength(14),
                    ])
                    ->action(function (array $data): void {
                        $user = Filament::auth()->user();
                        $firm = AccountSwitcher::managedFirm($user);
                        $member = AccountSwitcher::memberFor($user instanceof User ? $user : null);
                        if ($firm === null || $member === null) {
                            return;
                        }

                        try {
                            $svc = app(FirmMembershipService::class);
                            $tenant = $svc->createSubAccount($firm, (string) $data['name'], filled($data['niu'] ?? null) ? (string) $data['niu'] : null);
                            // The creator manages what they create — and it appears
                            // in their workspace switcher immediately.
                            $svc->assign($member, (string) $tenant->getTenantKey(), UserRole::Admin, (int) $member->getKey());
                        } catch (FirmException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title(__('firm.console.account_created'))->success()->send();
                    }),
            ]);
    }
}
