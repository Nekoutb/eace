<?php

namespace App\Filament\Resources\DataBreaches;

use App\Filament\Resources\DataBreaches\Pages\ListDataBreaches;
use App\Filament\Resources\DataBreaches\Tables\DataBreachesTable;
use App\Models\DataBreach;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Breach register (Loi 2024/017 art. 22). Central staff record each personal-data
 * breach and when the Authority + affected subjects were notified "without delay".
 */
class DataBreachResource extends Resource
{
    protected static ?string $model = DataBreach::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->isCentralUser() === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return (string) __('dp.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('dp.nav.breaches');
    }

    public static function getModelLabel(): string
    {
        return (string) __('dp.nav.breaches');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('dp.nav.breaches');
    }

    public static function canCreate(): bool
    {
        return false; // created via the "Record a breach" header action
    }

    /**
     * The breach form, shared by the create header action and the edit row action.
     *
     * @return array<int, mixed>
     */
    public static function formSchema(): array
    {
        return [
            DateTimePicker::make('detected_at')->label(__('dp.breach.detected_at'))->required()->default(now()),
            Textarea::make('description')->label(__('dp.breach.description'))->required()->rows(3)->maxLength(4000),
            TextInput::make('categories')->label(__('dp.breach.categories'))->maxLength(255),
            TextInput::make('volume')->label(__('dp.breach.volume'))->maxLength(255),
            Select::make('risk')->label(__('dp.breach.risk'))->options([
                'low' => (string) __('dp.breach.risk_low'),
                'medium' => (string) __('dp.breach.risk_medium'),
                'high' => (string) __('dp.breach.risk_high'),
            ])->default('medium')->required(),
            Textarea::make('measures')->label(__('dp.breach.measures'))->rows(3)->maxLength(4000),
            Select::make('status')->label(__('dp.breach.status'))->options([
                'open' => (string) __('dp.breach.st_open'),
                'contained' => (string) __('dp.breach.st_contained'),
                'closed' => (string) __('dp.breach.st_closed'),
            ])->default('open')->required(),
            DateTimePicker::make('authority_notified_at')->label(__('dp.breach.authority_notified_at')),
            DateTimePicker::make('subjects_notified_at')->label(__('dp.breach.subjects_notified_at')),
        ];
    }

    public static function table(Table $table): Table
    {
        return DataBreachesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDataBreaches::route('/'),
        ];
    }
}
