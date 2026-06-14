<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

/**
 * Livewire's update endpoint is a single global route shared by every panel, so
 * it can't carry the app panel's domain-tenancy middleware directly — that would
 * break the central admin panel's Livewire requests (localhost has no tenant).
 *
 * This makes the route "universal": on a tenant domain it initialises the tenant
 * (so the app panel's Livewire actions — including login — run against the tenant
 * database), and on a central domain it passes straight through as central.
 *
 * It extends InitializeTenancyByDomain so Laravel's middleware-priority sorting
 * still bumps it ahead of StartSession (matching the parent class), keeping the
 * session on the correct connection.
 */
class InitializeTenancyForLivewire extends InitializeTenancyByDomain
{
    public function handle($request, Closure $next)
    {
        $centralDomains = (array) config('tenancy.central_domains', []);

        if (in_array($request->getHost(), $centralDomains, true)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
