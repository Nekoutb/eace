<?php

namespace App\Filament\Resources\Conversations;

use App\Domain\Support\ConversationStatus;
use App\Filament\Resources\Conversations\Pages\ListConversations;
use App\Filament\Resources\Conversations\Tables\ConversationsTable;
use App\Models\Conversation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * The customer-service console: every workspace's conversations in one inbox, where
 * support staff triage, reply, assign, resolve and escalate. Central (landlord +
 * support staff) only — no tenant user can read another workspace's requests.
 */
class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = -6;

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
        return (string) __('support.nav.conversations');
    }

    public static function getModelLabel(): string
    {
        return (string) __('support.nav.conversations');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('support.nav.conversations');
    }

    /** A badge of conversations awaiting first triage, so the backlog is visible at a glance. */
    public static function getNavigationBadge(): ?string
    {
        $new = Conversation::query()->where('status', ConversationStatus::New->value)->count();

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
        return ConversationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
        ];
    }
}
