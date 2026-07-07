<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks every signed-in page of a SUSPENDED workspace (e.g. unpaid bills): the
 * user is signed out and sent back to the sign-in page, where the suspension
 * message is shown on their next attempt. Sits in the app panel's auth middleware
 * (page loads); the Livewire update route enforces the same rule in
 * InitializeTenancyForLivewire. Data is never touched — suspension only gates access.
 */
class EnsureWorkspaceNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        if (tenant()?->isSuspended() === true) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, (string) __('app.suspended.message'));
        }

        return $next($request);
    }
}
