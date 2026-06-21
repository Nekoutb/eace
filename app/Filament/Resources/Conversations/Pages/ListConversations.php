<?php

namespace App\Filament\Resources\Conversations\Pages;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Widgets\CustomerServiceStats;
use Filament\Resources\Pages\ListRecords;

class ListConversations extends ListRecords
{
    protected static string $resource = ConversationResource::class;

    /** The customer-experience KPI strip above the inbox. */
    protected function getHeaderWidgets(): array
    {
        return [
            CustomerServiceStats::class,
        ];
    }
}
