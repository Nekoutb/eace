<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the /app panel, auto-exit an impersonation session that has run past its
 * lifetime — the admin is restored and sent back to /admin. A no-op for normal
 * (non-impersonated) client sessions.
 */
final class EnforceImpersonationReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Impersonation::isActive() && Impersonation::expired()) {
            Impersonation::stop();

            return redirect('/admin');
        }

        return $next($request);
    }
}
