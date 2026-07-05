<?php

namespace App\Filament\Pages\ClientActivity;

use App\Models\Tenant;
use App\Services\Activity\ClientActivityService;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Daily activity line chart for the Client activity page: invoices recorded,
 * payments checked (settlement) and ACF certificates processed over the last
 * 30 days. The widget filter switches between all clients combined and one client.
 * Not discovered on the dashboard — rendered only by the ClientActivity page.
 */
class ActivityChart extends ChartWidget
{
    public ?string $filter = 'all';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '340px';

    public function getHeading(): string|Htmlable|null
    {
        return (string) __('activity.chart_heading');
    }

    public function getDescription(): string|Htmlable|null
    {
        return (string) __('activity.chart_desc');
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<scalar, scalar>
     */
    protected function getFilters(): ?array
    {
        $filters = ['all' => (string) __('activity.all_clients')];

        foreach (Tenant::query()->cursor() as $tenant) {
            $filters[(string) $tenant->getTenantKey()] = (string) ($tenant->name ?? $tenant->getTenantKey());
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $tenantId = $this->filter !== null && $this->filter !== 'all' ? $this->filter : null;

        $series = app(ClientActivityService::class)->dailySeries($tenantId, 30);

        return [
            'labels' => $series['labels'],
            'datasets' => [
                [
                    'label' => (string) __('activity.ds_recorded'),
                    'data' => $series['recorded'],
                    'borderColor' => '#16A34A',
                    'backgroundColor' => 'rgba(22,163,74,.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => (string) __('activity.ds_settled'),
                    'data' => $series['settled'],
                    'borderColor' => '#111111',
                    'backgroundColor' => 'rgba(17,17,17,.08)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => (string) __('activity.ds_acfs'),
                    'data' => $series['acfs'],
                    'borderColor' => '#E2382B',
                    'backgroundColor' => 'rgba(226,56,43,.10)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ];
    }
}
