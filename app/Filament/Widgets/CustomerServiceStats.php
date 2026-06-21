<?php

namespace App\Filament\Widgets;

use App\Domain\Support\ConversationNature;
use App\Models\Conversation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The customer-experience KPI strip above the support inbox. Each tile carries its
 * value AND its direction/target so a green/amber/red read is unambiguous:
 *
 *  - Open backlog (now)        — conversations still demanding a response/resolution
 *  - First response (median)   — minutes to the first reply over the last 30 days
 *  - Resolution time (median)  — open → resolved over the last 30 days
 *  - SLA attainment            — % answered within target
 *  - Complaint rate            — complaints ÷ all requests
 *  - Escalation rate           — % handed to a human
 *  - Resolved (30 days)        — throughput
 *  - CSAT                      — reserved; lights up with the surveys phase
 */
class CustomerServiceStats extends StatsOverviewWidget
{
    protected ?string $heading = null;

    public static function canView(): bool
    {
        return auth()->user()?->isCentralUser() === true;
    }

    protected function getStats(): array
    {
        $recent = Conversation::query()->where('created_at', '>=', now()->subDays(30))->get();
        $total = $recent->count();

        $backlog = Conversation::query()->open()->count();

        $frt = $this->median($recent->map(fn (Conversation $c): ?int => $c->firstResponseMinutes())->all());
        $resolved = $recent->filter(fn (Conversation $c): bool => $c->resolved_at !== null);
        $ttr = $this->median($resolved->map(fn (Conversation $c): ?int => $c->resolutionMinutes())->all());

        $answered = $recent->filter(fn (Conversation $c): bool => $c->first_response_at !== null);
        $slaOk = $answered->filter(fn (Conversation $c): bool => $c->sla_due_at === null || $c->first_response_at->lessThanOrEqualTo($c->sla_due_at))->count();
        $sla = $answered->count() > 0 ? (int) round($slaOk / $answered->count() * 100) : null;

        $complaints = $recent->filter(fn (Conversation $c): bool => $c->nature === ConversationNature::Complaint)->count();
        $complaintRate = $total > 0 ? (int) round($complaints / $total * 100) : 0;

        $escalated = $recent->filter(fn (Conversation $c): bool => $c->escalated_at !== null)->count();
        $escalationRate = $total > 0 ? (int) round($escalated / $total * 100) : 0;

        return [
            Stat::make(__('support.kpi.open_backlog'), (string) $backlog)
                ->description(__('support.kpi.open_backlog_desc'))
                ->descriptionIcon('heroicon-m-inbox-stack')
                ->color($backlog === 0 ? 'success' : ($backlog >= 10 ? 'danger' : 'warning')),

            Stat::make(__('support.kpi.frt'), $this->formatMinutes($frt))
                ->description(__('support.kpi.frt_desc'))
                ->descriptionIcon('heroicon-m-bolt')
                ->color($frt === null ? 'gray' : ($frt <= 240 ? 'success' : ($frt <= 1440 ? 'warning' : 'danger'))),

            Stat::make(__('support.kpi.ttr'), $this->formatMinutes($ttr))
                ->description(__('support.kpi.ttr_desc'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($ttr === null ? 'gray' : ($ttr <= 1440 ? 'success' : ($ttr <= 4320 ? 'warning' : 'danger'))),

            Stat::make(__('support.kpi.sla'), $sla === null ? '—' : $sla.'%')
                ->description(__('support.kpi.sla_desc'))
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($sla === null ? 'gray' : ($sla >= 90 ? 'success' : ($sla >= 75 ? 'warning' : 'danger'))),

            Stat::make(__('support.kpi.complaint_rate'), $complaintRate.'%')
                ->description(__('support.kpi.complaint_rate_desc'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($complaintRate <= 10 ? 'success' : ($complaintRate <= 25 ? 'warning' : 'danger')),

            Stat::make(__('support.kpi.escalation_rate'), $escalationRate.'%')
                ->description(__('support.kpi.escalation_rate_desc'))
                ->descriptionIcon('heroicon-m-arrow-up-circle')
                ->color($escalationRate <= 30 ? 'success' : 'warning'),

            Stat::make(__('support.kpi.resolved_30d'), (string) $resolved->count())
                ->description(__('support.kpi.resolved_30d_desc'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('support.kpi.csat'), '—')
                ->description(__('support.kpi.csat_desc'))
                ->descriptionIcon('heroicon-m-face-smile')
                ->color('gray'),
        ];
    }

    /**
     * @param  array<int, int|null>  $values
     */
    private function median(array $values): ?int
    {
        $clean = array_values(array_filter($values, fn (?int $v): bool => $v !== null));
        if ($clean === []) {
            return null;
        }

        sort($clean);
        $n = count($clean);
        $mid = intdiv($n, 2);

        return $n % 2 === 1
            ? (int) $clean[$mid]
            : (int) round(((int) $clean[$mid - 1] + (int) $clean[$mid]) / 2);
    }

    private function formatMinutes(?int $minutes): string
    {
        if ($minutes === null) {
            return (string) __('support.kpi.none');
        }
        if ($minutes < 60) {
            return (string) __('support.kpi.minutes', ['n' => $minutes]);
        }
        if ($minutes < 1440) {
            return (string) __('support.kpi.hours', ['n' => round($minutes / 60, 1)]);
        }

        return (string) __('support.kpi.days', ['n' => round($minutes / 1440, 1)]);
    }
}
