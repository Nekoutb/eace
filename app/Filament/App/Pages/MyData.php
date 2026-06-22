<?php

namespace App\Filament\App\Pages;

use App\Models\DataAccessLog;
use App\Models\User;
use App\Services\DataProtection\DataSubjectService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The signed-in customer's data-protection self-service (Loi 2024/017 art. 39/43):
 * read the privacy notice, download a copy of their data, and lodge a request
 * (access / rectification / erasure / objection / portability).
 */
class MyData extends Page
{
    protected string $view = 'filament.app.pages.my-data';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 26;

    public static function getNavigationLabel(): string
    {
        return (string) __('dp.nav.my_data');
    }

    public function getTitle(): string
    {
        return (string) __('dp.my.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('dp.my.export_btn'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->downloadExport()),

            Action::make('request')
                ->label(__('dp.my.req_send'))
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->modalHeading(__('dp.my.request_title'))
                ->schema([
                    Select::make('type')->label(__('dp.my.req_type'))->options([
                        'access' => (string) __('dp.request.t_access'),
                        'rectification' => (string) __('dp.request.t_rectification'),
                        'erasure' => (string) __('dp.request.t_erasure'),
                        'objection' => (string) __('dp.request.t_objection'),
                        'portability' => (string) __('dp.request.t_portability'),
                    ])->required(),
                    Textarea::make('details')->label(__('dp.my.req_details'))->rows(3)->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();
                    if (! $user instanceof User) {
                        return;
                    }

                    app(DataSubjectService::class)->lodgeRequest($user, (string) $data['type'], $data['details'] ?? null);

                    Notification::make()
                        ->title(__('dp.my.req_sent', ['days' => (int) config('niu.data_protection.dsar_reply_days', 30)]))
                        ->success()
                        ->send();
                }),
        ];
    }

    /** Stream a JSON copy of the user's own data, and log the export (art. 27). */
    public function downloadExport(): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $data = app(DataSubjectService::class)->exportFor($user);
        DataAccessLog::record('export', 'own-data');

        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(
            fn () => print ($json),
            'my-data-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json'],
        );
    }
}
