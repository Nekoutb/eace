<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Companion to ForcePasswordChange. That middleware only covers GET page loads
 * (panel auth middleware); Livewire actions are dispatched over the shared
 * /livewire/update POST route, which bypasses panel middleware. Without this, a
 * still-temporary-password user could drive privileged Livewire actions
 * (Settings::save, add user, …) directly without ever rotating their password.
 *
 * In tenant context, while the signed-in user still carries a temporary
 * password, this allows ONLY updates targeting the change-password component and
 * blocks everything else. Forging the component name to slip past would break
 * Livewire's downstream snapshot-checksum check, so no privileged action runs.
 * Central (admin) requests are untouched — tenancy is not initialised there.
 */
final class EnforcePasswordRotation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            return $next($request);
        }

        $user = $request->user();
        if ($user === null || ! ($user->must_change_password ?? false)) {
            return $next($request);
        }

        foreach ((array) $request->input('components', []) as $component) {
            $snapshot = is_array($component) ? ($component['snapshot'] ?? '{}') : '{}';
            $decoded = is_string($snapshot) ? json_decode($snapshot, true) : null;
            $name = is_array($decoded) ? ($decoded['memo']['name'] ?? '') : '';

            if (! str_contains((string) $name, 'change-password')) {
                abort(403, 'You must change your temporary password before continuing.');
            }
        }

        return $next($request);
    }
}
