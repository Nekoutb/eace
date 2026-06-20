<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Account-based (no-subdomain) tenancy for the /app panel. The signed-in user's
 * workspace id is kept in the central session (set by the login once the email is
 * resolved through the login directory). This middleware reads it and initialises
 * that workspace, so every request — and the auth guard that follows — runs
 * against the right tenant database.
 *
 * It must run AFTER StartSession (to read the session) and BEFORE the panel's
 * auth/session middleware (so the user resolves against the tenant). The session
 * itself is pinned to the central connection, so reading it before the workspace
 * is initialised is safe (see config/session.php).
 *
 * A user can only ever reach the workspace stored on their own session, set at
 * login from their own directory entry — they cannot reach another's.
 */
class InitializeTenancyFromSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->session()->get('tenant_id');

        if (is_string($tenantId) && $tenantId !== '' && tenant() === null) {
            $tenant = Tenant::find($tenantId);

            if ($tenant !== null) {
                tenancy()->initialize($tenant);
            } else {
                // The workspace was removed — drop the stale pointer so the user
                // is cleanly sent back to sign in rather than half-initialised.
                $request->session()->forget('tenant_id');
            }
        }

        return $next($request);
    }
}
