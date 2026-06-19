<?php

namespace App\Filament\Resources\SupportQueries;

use App\Filament\Resources\SupportQueries\Pages\ListSupportQueries;
use App\Filament\Resources\SupportQueries\Tables\SupportQueriesTable;
use App\Models\SupportQuery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Central admin inbox for the questions tenant staff raise from the home-page
 * chat box. The SaaS owner reads and answers them here; the reply is shown back
 * to the submitter inside their workspace.
 */
class SupportQueryResource extends Resource
{
    protected static ?string $model = SupportQuery::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = -5;

    /** Defence in depth: only central (landlord) users may read client questions. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isCentralUser() === true;
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('app.support.nav');
    }

    public static function getModelLabel(): string
    {
        return (string) __('app.support.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('app.support.nav');
    }

    /** A badge of unanswered questions so the owner sees a backlog at a glance. */
    public static function getNavigationBadge(): ?string
    {
        $new = SupportQuery::query()->where('status', 'new')->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return SupportQueriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportQueries::route('/'),
        ];
    }
}
