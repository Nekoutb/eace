<?php

declare(strict_types=1);

namespace App\Services\Activity;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Aggregates client activity for the operator's "Client activity" section: how many
 * invoices were recorded, payments checked (settlement) and ACF certificates
 * processed per DAY, per client. Each client's data lives in their own tenant
 * database, so this runs the counting inside each workspace and merges the results
 * centrally. Best-effort: a missing/broken tenant database counts as zero activity,
 * never an error. Results are cached briefly so the admin dashboard stays snappy.
 */
final class ClientActivityService
{
    private const CACHE_SECONDS = 300;

    /**
     * The daily activity series for one client (or every client combined) over the
     * trailing $days window, oldest day first.
     *
     * @return array{labels: list<string>, recorded: list<int>, settled: list<int>, acfs: list<int>}
     */
    public function dailySeries(?string $tenantId, int $days = 30): array
    {
        $days = max(1, min(120, $days));
        $from = Carbon::now()->startOfDay()->subDays($days - 1);

        // cursor(): plain lazy iteration typed to our Tenant (stancl's custom
        // TenantCollection erases the model type for static analysis).
        $tenants = $tenantId === null
            ? Tenant::query()->cursor()
            : Tenant::query()->whereKey($tenantId)->cursor();

        // date => [recorded, settled, acfs]
        $byDay = [];
        foreach ($tenants as $tenant) {
            foreach ($this->tenantDaily($tenant, $from) as $date => $counts) {
                $slot = $byDay[$date] ?? ['recorded' => 0, 'settled' => 0, 'acfs' => 0];
                $slot['recorded'] += $counts['recorded'];
                $slot['settled'] += $counts['settled'];
                $slot['acfs'] += $counts['acfs'];
                $byDay[$date] = $slot;
            }
        }

        $labels = [];
        $recorded = [];
        $settled = [];
        $acfs = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();
            $labels[] = $day->format('d/m');
            $recorded[] = $byDay[$key]['recorded'] ?? 0;
            $settled[] = $byDay[$key]['settled'] ?? 0;
            $acfs[] = $byDay[$key]['acfs'] ?? 0;
        }

        return ['labels' => $labels, 'recorded' => $recorded, 'settled' => $settled, 'acfs' => $acfs];
    }

    /**
     * Outreach summary for one client: recent volumes and when they were last active.
     *
     * @return array{recorded30: int, settled30: int, acfs30: int, total30: int, last: ?Carbon}
     */
    public function clientSummary(Tenant $tenant): array
    {
        $from = Carbon::now()->startOfDay()->subDays(29);
        $recorded = 0;
        $settled = 0;
        $acfs = 0;

        foreach ($this->tenantDaily($tenant, $from) as $counts) {
            $recorded += $counts['recorded'];
            $settled += $counts['settled'];
            $acfs += $counts['acfs'];
        }

        return [
            'recorded30' => $recorded,
            'settled30' => $settled,
            'acfs30' => $acfs,
            'total30' => $recorded + $settled + $acfs,
            'last' => $this->lastActivity($tenant),
        ];
    }

    /**
     * Per-day counts inside ONE workspace since $from: verifications split by stage
     * plus certificates processed. Cached briefly per tenant + window.
     *
     * @return array<string, array{recorded: int, settled: int, acfs: int}>
     */
    private function tenantDaily(Tenant $tenant, Carbon $from): array
    {
        $key = 'client-activity:daily:'.$tenant->getTenantKey().':'.$from->toDateString();

        /** @var array<string, array{recorded: int, settled: int, acfs: int}> */
        return Cache::remember($key, self::CACHE_SECONDS, function () use ($tenant, $from): array {
            try {
                return (array) $tenant->run(function () use ($from): array {
                    $out = [];

                    $verifications = DB::table('verifications')
                        ->selectRaw('date(created_at) as day, stage, count(*) as n')
                        ->where('created_at', '>=', $from)
                        ->groupBy('day', 'stage')
                        ->get();
                    foreach ($verifications as $row) {
                        $day = (string) $row->day;
                        $out[$day] ??= ['recorded' => 0, 'settled' => 0, 'acfs' => 0];
                        if ((string) $row->stage === 'settlement') {
                            $out[$day]['settled'] += (int) $row->n;
                        } else {
                            $out[$day]['recorded'] += (int) $row->n;
                        }
                    }

                    $certificates = DB::table('acf_certificates')
                        ->selectRaw('date(created_at) as day, count(*) as n')
                        ->where('created_at', '>=', $from)
                        ->groupBy('day')
                        ->get();
                    foreach ($certificates as $row) {
                        $day = (string) $row->day;
                        $out[$day] ??= ['recorded' => 0, 'settled' => 0, 'acfs' => 0];
                        $out[$day]['acfs'] += (int) $row->n;
                    }

                    return $out;
                });
            } catch (Throwable $e) {
                report($e);
                $this->restoreCentralContext();

                return []; // a broken/missing workspace database is zero activity
            }
        });
    }

    /**
     * Tenant::run() restores the previous context on success but NOT when the
     * callback throws (e.g. the workspace database file is missing) — the request
     * would stay pinned to the broken workspace and poison every later query.
     * This service is central/admin-only, so after a failure the right context is
     * always "no tenant".
     */
    private function restoreCentralContext(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }

    /** When this workspace last recorded, settled or processed anything, or null. */
    private function lastActivity(Tenant $tenant): ?Carbon
    {
        $key = 'client-activity:last:'.$tenant->getTenantKey();

        $value = Cache::remember($key, self::CACHE_SECONDS, function () use ($tenant): string {
            try {
                $last = $tenant->run(function (): ?string {
                    $verification = DB::table('verifications')->max('created_at');
                    $certificate = DB::table('acf_certificates')->max('created_at');

                    return max((string) $verification, (string) $certificate) ?: null;
                });

                return (string) ($last ?? '');
            } catch (Throwable $e) {
                report($e);
                $this->restoreCentralContext();

                return '';
            }
        });

        return $value === '' ? null : Carbon::parse($value);
    }
}
