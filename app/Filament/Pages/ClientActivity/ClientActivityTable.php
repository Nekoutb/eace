<?php

namespace App\Filament\Pages\ClientActivity;

use App\Models\Tenant;
use App\Services\Activity\ClientActivityService;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

/**
 * Per-client outreach table for the Client activity page: 30-day volumes (invoices
 * recorded, payments checked, ACFs processed) and when the client was last active —
 * colour-coded so a workspace going quiet is the first thing the operator sees.
 * Not discovered on the dashboard — rendered only by the ClientActivity page.
 */
class ClientActivityTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading((string) __('activity.table_heading'))
            ->description((string) __('activity.table_desc'))
            ->query(Tenant::query())
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.client.org_name'))
                    ->weight('bold')
                    ->getStateUsing(fn (Tenant $record): string => (string) ($record->name ?? $record->getTenantKey())),
                TextColumn::make('id')
                    ->label(__('app.client.identifier'))
                    ->fontFamily(FontFamily::Mono)
                    ->badge()
                    ->color('gray'),
                TextColumn::make('recorded30')
                    ->label(__('activity.col_recorded'))
                    ->getStateUsing(fn (Tenant $record): int => $this->summary($record)['recorded30'])
                    ->alignCenter(),
                TextColumn::make('settled30')
                    ->label(__('activity.col_settled'))
                    ->getStateUsing(fn (Tenant $record): int => $this->summary($record)['settled30'])
                    ->alignCenter(),
                TextColumn::make('acfs30')
                    ->label(__('activity.col_acfs'))
                    ->getStateUsing(fn (Tenant $record): int => $this->summary($record)['acfs30'])
                    ->alignCenter(),
                TextColumn::make('total30')
                    ->label(__('activity.col_total'))
                    ->getStateUsing(fn (Tenant $record): int => $this->summary($record)['total30'])
                    ->weight('bold')
                    ->alignCenter(),
                TextColumn::make('last_activity')
                    ->label(__('activity.col_last'))
                    ->badge()
                    ->getStateUsing(function (Tenant $record): string {
                        $last = $this->summary($record)['last'];

                        return $last instanceof Carbon
                            ? $last->diffForHumans()
                            : (string) __('activity.never');
                    })
                    ->color(function (Tenant $record): string {
                        $last = $this->summary($record)['last'];
                        if (! $last instanceof Carbon) {
                            return 'gray';
                        }
                        if ($last->lt(now()->subDays(14))) {
                            return 'danger';
                        }

                        return $last->lt(now()->subDays(7)) ? 'warning' : 'success';
                    })
                    ->tooltip(fn (Tenant $record): ?string => $this->summary($record)['last']?->format('d/m/Y H:i')),
            ]);
    }

    /**
     * @var array<string, array{recorded30: int, settled30: int, acfs30: int, total30: int, last: ?Carbon}>
     */
    private array $summaries = [];

    /**
     * @return array{recorded30: int, settled30: int, acfs30: int, total30: int, last: ?Carbon}
     */
    private function summary(Tenant $tenant): array
    {
        return $this->summaries[(string) $tenant->getTenantKey()]
            ??= app(ClientActivityService::class)->clientSummary($tenant);
    }
}
