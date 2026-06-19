<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

/**
 * Livewire's update endpoint is a single global route shared by every panel, so
 * it can't carry the app panel's domain-tenancy middleware directly — that would
 * break the central admin panel's Livewire requests (which must stay central).
 *
 * This makes the route "universal": on a tenant domain it initialises the tenant
 * (so the app panel's Livewire actions — including login — run against the tenant
 * database), and on a central domain it passes straight through as central.
 *
 * Single-workspace twist: when one organisation is hosted on the bare domain
 * (e.g. eace.cm-ea.com), that host is BOTH the tenant domain AND the host of the
 * central /admin console. A host-only test would force every admin-panel Livewire
 * action into tenant context, so the central admin could never authenticate.
 * Therefore a Livewire request that originates from the central admin panel also
 * passes through as central, regardless of host.
 *
 * It extends InitializeTenancyByDomain so Laravel's middleware-priority sorting
 * still bumps it ahead of StartSession (matching the parent class), keeping the
 * session on the correct connection.
 */
class InitializeTenancyForLivewire extends InitializeTenancyByDomain
{
    /** The central (landlord) admin panel path — must match AdminPanelProvider::path(). */
    private const CENTRAL_PANEL_PATH = 'admin';

    public function handle($request, Closure $next)
    {
        $centralDomains = (array) config('tenancy.central_domains', []);

        if (in_array($request->getHost(), $centralDomains, true) || $this->isCentralPanelRequest($request)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    /**
     * Whether this Livewire update request came from the central admin panel. The
     * originating page lives in each component snapshot's memo path; the Referer
     * header is a fallback when the snapshot can't be read.
     */
    private function isCentralPanelRequest(Request $request): bool
    {
        foreach ((array) $request->input('components', []) as $component) {
            $snapshot = is_array($component) ? ($component['snapshot'] ?? null) : null;

            if (is_string($snapshot)) {
                $memo = json_decode($snapshot, true);

                if (is_array($memo) && $this->pathIsCentralPanel((string) ($memo['memo']['path'] ?? ''))) {
                    return true;
                }
            }
        }

        $refererPath = (string) parse_url((string) $request->headers->get('referer', ''), PHP_URL_PATH);

        return $this->pathIsCentralPanel($refererPath);
    }

    private function pathIsCentralPanel(string $path): bool
    {
        $path = ltrim($path, '/');
        $panel = self::CENTRAL_PANEL_PATH;

        return $path === $panel || str_starts_with($path, $panel.'/');
    }
}
