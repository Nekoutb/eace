<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\MonitoredService;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pings a service's health URL and records the result on the row. Best-effort — a
 * network failure is simply recorded as "down", never thrown.
 *
 * Note: this runs ON the server, so it can confirm OTHER endpoints are reachable but
 * cannot detect this server's own downtime. Pair it with an external monitor
 * (e.g. UptimeRobot) for true outage alerting of the primary site.
 */
final class UptimeChecker
{
    public function check(MonitoredService $service): void
    {
        if (blank($service->health_url)) {
            return;
        }

        $status = 'down';
        $code = null;

        try {
            $response = Http::connectTimeout(8)->timeout(12)->get((string) $service->health_url);
            $code = $response->status();
            $status = ($code >= 200 && $code < 400) ? 'up' : 'down';
        } catch (Throwable $e) {
            report($e);
        }

        $service->forceFill([
            'last_status' => $status,
            'last_http_code' => $code,
            'last_checked_at' => now(),
        ])->save();
    }
}
