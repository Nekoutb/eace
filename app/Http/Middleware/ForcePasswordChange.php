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
 *
 * The mandatory two-factor enrolment pages are also exempt: when a user has
 * BOTH a temporary password AND no 2FA yet, Filament's MFA gate sends them to
 * the set-up page first — without this exemption the two gates would redirect
 * to each other forever (ERR_TOO_MANY_REDIRECTS). MFA enrolment runs first,
 * then the password change.
 */
final class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && ($user->must_change_password ?? false)
            && ! $request->routeIs('filament.app.pages.change-password')
            && ! $request->routeIs('filament.app.auth.logout')
            && ! $request->is('*multi-factor-authentication*')) {
            return redirect()->route('filament.app.pages.change-password');
        }

        return $next($request);
    }
}
