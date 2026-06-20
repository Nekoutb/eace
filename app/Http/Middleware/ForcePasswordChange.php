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
 * The mandatory two-factor enrolment pages are exempt. On first login a user
 * with a temporary password has no 2FA yet, so Filament's MFA gate sends them to
 * the set-up page first; without this exemption that page would redirect to
 * change-password while the MFA gate redirects back, looping forever
 * (ERR_TOO_MANY_REDIRECTS). So 2FA enrolment runs first, then the password
 * change (EnforcePasswordRotation lets the enrolment Livewire actions through).
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
