<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Both panels. A user still carrying a temporary password (must_change_password)
 * is redirected to the change-password page of the CURRENT panel on every page
 * load until they set their own — tenant staff on /app, customer-service staff on
 * /admin. Livewire interactions go through the shared /livewire/update route,
 * which this never touches, so the change form itself keeps working; logout stays
 * reachable so nobody is trapped.
 *
 * The mandatory two-factor enrolment pages are exempt. On first login a user
 * with a temporary password has no 2FA yet, so Filament's MFA gate sends them to
 * the set-up page first; without this exemption that page would redirect to
 * change-password while the MFA gate redirects back, looping forever
 * (ERR_TOO_MANY_REDIRECTS). So 2FA enrolment runs first, then the password change.
 */
final class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        // While an admin impersonates a workspace (read-only) never force a password
        // change — they are only viewing, and the change page is itself read-only,
        // which would otherwise trap them.
        if (Impersonation::isActive()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null || ! ($user->must_change_password ?? false)) {
            return $next($request);
        }

        $panel = Filament::getCurrentPanel()?->getId() ?? 'app';
        $changeRoute = "filament.{$panel}.pages.change-password";

        if (! $request->routeIs($changeRoute)
            && ! $request->routeIs("filament.{$panel}.auth.logout")
            && ! $request->is('*multi-factor-authentication*')) {
            return redirect()->route($changeRoute);
        }

        return $next($request);
    }
}
