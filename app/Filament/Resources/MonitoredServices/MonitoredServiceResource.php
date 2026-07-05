<?php

namespace App\Filament\Resources\MonitoredServices;

use App\Domain\Monitoring\ServiceCategory;
use App\Filament\Resources\MonitoredServices\Pages\ListMonitoredServices;
use App\Filament\Resources\MonitoredServices\Tables\MonitoredServicesTable;
use App\Models\MonitoredService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Tables\Table;

/**
 * Central admin. The operations register: every subscription / infrastructure
 * service behind the websites (hosting, domain, storage, email, API, SSL), with its
 * renewal date, amount, auto-pay flag and optional health URL. Powers the renewal
 * reminders + the uptime ping. Operators (landlord) only.
 */
class MonitoredServiceResource extends Resource
{
    protected static ?string $model = MonitoredService::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->isCentralUser() === true;
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('monitoring.nav');
    }

    public static function getModelLabel(): string
    {
        return (string) __('monitoring.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('monitoring.plural');
    }

    /**
     * Shared form (add + edit).
     *
     * @return array<int, Component>
     */
    public static function formSchema(): array
    {
        return [
            TextInput::make('name')->label(__('monitoring.f_name'))->required()->maxLength(255),
            TextInput::make('provider')->label(__('monitoring.f_provider'))->maxLength(255)->placeholder('Vultr, Wasabi, Hostinger…'),
            Select::make('category')->label(__('monitoring.f_category'))->options(ServiceCategory::options())->default('other')->required()->native(false),
            TextInput::make('powers')->label(__('monitoring.f_powers'))->maxLength(255)->placeholder('eace.cm-ea.com'),
            TextInput::make('amount')->label(__('monitoring.f_amount'))->numeric()->minValue(0),
            TextInput::make('currency')->label(__('monitoring.f_currency'))->default('USD')->maxLength(8),
            Select::make('billing_cycle')->label(__('monitoring.f_cycle'))->options([
                'monthly' => __('monitoring.cycle.monthly'),
                'quarterly' => __('monitoring.cycle.quarterly'),
                'yearly' => __('monitoring.cycle.yearly'),
                'one_off' => __('monitoring.cycle.one_off'),
            ])->default('yearly')->required()->native(false),
            DatePicker::make('renews_at')->label(__('monitoring.f_renews_at'))->native(false),
            Toggle::make('auto_pay')->label(__('monitoring.f_auto_pay'))->helperText(__('monitoring.f_auto_pay_help')),
            TextInput::make('health_url')->label(__('monitoring.f_health_url'))->url()->maxLength(255)->placeholder('https://eace.cm-ea.com'),
            Toggle::make('is_active')->label(__('monitoring.f_active'))->default(true),
            Textarea::make('notes')->label(__('monitoring.f_notes'))->rows(2)->maxLength(1000)->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return MonitoredServicesTable::configure($table);
    }

    /** Created via the guided "Add a service" action, not a blank page. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonitoredServices::route('/'),
        ];
    }
}
