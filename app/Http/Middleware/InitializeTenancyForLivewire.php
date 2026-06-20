<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Livewire's update endpoint is a single global route shared by the central
 * /admin panel and the tenant /app panel. Under account-based (no-subdomain)
 * tenancy the panel a request belongs to is decided ONLY by server-side session
 * state — never by anything in the request the caller controls:
 *
 *  - a session carrying a workspace id (`tenant_id`) is a tenant session, so we
 *    initialise that workspace; a signed-in tenant user can therefore never be
 *    pushed into central (landlord) context by spoofing the Livewire snapshot's
 *    memo path or the Referer header;
 *  - a session with no workspace id is a genuine landlord session and stays
 *    central.
 *
 * Central (landlord) users have no directory entry, so the login never writes a
 * `tenant_id` for them; tenant users cannot reach /admin. The two session kinds
 * are therefore disjoint, and the session id alone is an authoritative,
 * untamperable panel signal.
 *
 * Runs after the `web` group (so StartSession has populated the session) and the
 * session is pinned to the central connection, so reading the workspace id before
 * initialising it is safe.
 */
class InitializeTenancyForLivewire
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->session()->get('tenant_id');

        if (is_string($tenantId) && $tenantId !== '' && tenant() === null) {
            $tenant = Tenant::find($tenantId);

            if ($tenant !== null) {
                tenancy()->initialize($tenant);
            } else {
                // The workspace was removed — drop the stale pointer so the user is
                // cleanly sent back to sign in rather than half-initialised.
                $request->session()->forget('tenant_id');
            }
        }

        return $next($request);
    }
}
