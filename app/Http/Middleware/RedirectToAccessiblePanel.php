<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Both panels share one host. A signed-in user belongs to exactly one of them —
 * the landlord to /admin, a client (tenant) user to /app. Without this, a user
 * who lands on the wrong panel (e.g. the landlord signing in at /app, or anyone
 * following a stale link) hits Filament's hard 403 from canAccessPanel().
 *
 * This sends an authenticated user who cannot access the CURRENT panel to the one
 * they can, instead of a dead end. Anonymous visitors pass through untouched so
 * the login page still renders. There is no redirect loop: every user can access
 * exactly one panel, so the target panel always accepts them.
 */
class RedirectToAccessiblePanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel();
        $user = $request->user();

        if ($panel !== null && $user !== null && ! $user->canAccessPanel($panel)) {
            return redirect($panel->getId() === 'app' ? '/admin' : '/app');
        }

        return $next($request);
    }
}
