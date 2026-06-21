<?php

namespace App\Filament\Resources\SurveyResponses;

use App\Filament\Resources\SurveyResponses\Pages\ListSurveyResponses;
use App\Filament\Resources\SurveyResponses\Tables\SurveyResponsesTable;
use App\Models\SurveyResponse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * The anonymous customer-experience survey results — read-only, central staff only.
 * Responses are tagged by workspace but carry no individual identity. The headline
 * CSAT/NPS sit on the conversations dashboard; this is for reading the detail and
 * the free-text comments.
 */
class SurveyResponseResource extends Resource
{
    protected static ?string $model = SurveyResponse::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = -3;

    public static function canAccess(): bool
    {
        return auth()->user()?->isCentralUser() === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return (string) __('support.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('support.survey.nav');
    }

    public static function getModelLabel(): string
    {
        return (string) __('support.survey.nav');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('support.survey.nav');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return SurveyResponsesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSurveyResponses::route('/'),
        ];
    }
}
