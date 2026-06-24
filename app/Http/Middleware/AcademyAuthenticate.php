<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AcademyUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the /academy training platform behind the `academy` guard. Unauthenticated
 * visitors — or accounts an operator has de-activated — are sent to the academy
 * sign-in. Simple email + password only; there is no two-factor here.
 */
final class AcademyAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('academy')->user();

        if (! $user instanceof AcademyUser || $user->is_active !== true) {
            Auth::guard('academy')->logout();

            return redirect()->guest(route('academy.login'));
        }

        return $next($request);
    }
}
