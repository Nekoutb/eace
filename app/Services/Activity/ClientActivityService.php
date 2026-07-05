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
     * The adoption steps every client is expected to complete, in journey order.
     * Keys are used for lang labels (activity.step_*) and the matrix cells.
     *
     * @return list<string>
     */
    public static function adoptionSteps(): array
    {
        return ['signed_in', 'two_fa', 'settings', 'team', 'acf', 'recording', 'settlement'];
    }

    /**
     * The adoption matrix: for every client, whether each expected usage step has
     * been reached — detected from their workspace data (sign-ins, 2FA enrolment,
     * company settings, team, ACFs, recording and settlement controls). Powers the
     * "engage the client at the right moment" grid.
     *
     * @return list<array{id: string, name: string, completed: int, steps: array<string, array{done: bool, detail: ?string}>}>
     */
    public function adoptionMatrix(): array
    {
        $clients = [];

        foreach (Tenant::query()->cursor() as $tenant) {
            $steps = $this->tenantAdoption($tenant);

            $clients[] = [
                'id' => (string) $tenant->getTenantKey(),
                'name' => (string) ($tenant->name ?? $tenant->getTenantKey()),
                'completed' => count(array_filter($steps, fn (array $s): bool => $s['done'])),
                'steps' => $steps,
            ];
        }

        return $clients;
    }

    /**
     * Detect ONE workspace's adoption state in a single pass. Cached briefly; a
     * broken workspace database reads as "nothing reached yet".
     *
     * @return array<string, array{done: bool, detail: ?string}>
     */
    private function tenantAdoption(Tenant $tenant): array
    {
        $key = 'client-activity:adoption:'.$tenant->getTenantKey();

        /** @var array<string, array{done: bool, detail: ?string}> */
        return Cache::remember($key, self::CACHE_SECONDS, function () use ($tenant): array {
            try {
                return (array) $tenant->run(function (): array {
                    $users = (int) DB::table('users')->count();
                    $signedIn = (int) DB::table('users')->whereNotNull('last_login_at')->count();
                    $lastLogin = DB::table('users')->max('last_login_at');
                    $twoFa = (int) DB::table('users')->whereNotNull('app_authentication_secret')->count();

                    $profile = DB::table('organization_profile')
                        ->whereNotNull('entity_name')->where('entity_name', '!=', '')
                        ->whereNotNull('entity_niu')->where('entity_niu', '!=', '')
                        ->value('entity_name');

                    $acfs = (int) DB::table('acf_certificates')->count();
                    $recorded = (int) DB::table('verifications')->where('stage', 'recording')->count();
                    $settled = (int) DB::table('verifications')->where('stage', 'settlement')->count();

                    return [
                        'signed_in' => ['done' => $signedIn > 0, 'detail' => is_string($lastLogin) ? Carbon::parse($lastLogin)->format('d/m/Y') : null],
                        'two_fa' => ['done' => $twoFa > 0, 'detail' => $twoFa > 0 ? $twoFa.'/'.$users : null],
                        'settings' => ['done' => is_string($profile) && $profile !== '', 'detail' => is_string($profile) && $profile !== '' ? $profile : null],
                        'team' => ['done' => $users > 1, 'detail' => (string) $users],
                        'acf' => ['done' => $acfs > 0, 'detail' => $acfs > 0 ? (string) $acfs : null],
                        'recording' => ['done' => $recorded > 0, 'detail' => $recorded > 0 ? (string) $recorded : null],
                        'settlement' => ['done' => $settled > 0, 'detail' => $settled > 0 ? (string) $settled : null],
                    ];
                });
            } catch (Throwable $e) {
                report($e);
                $this->restoreCentralContext();

                return array_fill_keys(self::adoptionSteps(), ['done' => false, 'detail' => null]);
            }
        });
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
