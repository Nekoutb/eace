<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AcademyUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after AcademyAuthenticate: a member still carrying the one-time password
 * they were emailed (must_change_password) is funnelled to the change-password
 * page until they set their own. The change-password + logout routes sit OUTSIDE
 * this group so they stay reachable.
 */
final class ForceAcademyPasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('academy')->user();

        if ($user instanceof AcademyUser && $user->must_change_password === true) {
            return redirect()->route('academy.password');
        }

        return $next($request);
    }
}
