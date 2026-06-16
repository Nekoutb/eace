<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant app panel only. A user still carrying a temporary password
 * (must_change_password) is redirected to the change-password page on every
 * page load until they set their own. Livewire interactions go through the
 * shared /livewire/update route, which this never touches, so the change
 * form itself keeps working; logout stays reachable so nobody is trapped.
 */
final class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && ($user->must_change_password ?? false)
            && ! $request->routeIs('filament.app.pages.change-password')
            && ! $request->routeIs('filament.app.auth.logout')) {
            return redirect()->route('filament.app.pages.change-password');
        }

        return $next($request);
    }
}
