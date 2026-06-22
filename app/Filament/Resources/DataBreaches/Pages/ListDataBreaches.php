<?php

namespace App\Filament\Resources\DataBreaches\Pages;

use App\Filament\Resources\DataBreaches\DataBreachResource;
use App\Models\DataBreach;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDataBreaches extends ListRecords
{
    protected static string $resource = DataBreachResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('record')
                ->label(__('dp.breach.record'))
                ->icon('heroicon-o-plus')
                ->modalHeading(__('dp.breach.record'))
                ->schema(DataBreachResource::formSchema())
                ->action(function (array $data): void {
                    $data['reported_by'] = auth()->user()?->name;
                    DataBreach::query()->create($data);

                    Notification::make()->title(__('dp.breach.saved'))->success()->send();
                }),
        ];
    }
}
