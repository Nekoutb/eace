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
 * password, this allows ONLY the two legitimate first-login setup flows — the
 * change-password page and the mandatory 2FA enrolment page (which Filament
 * forces before the password change) — and blocks every other action. Forging
 * the snapshot to fake the page would break Livewire's downstream
 * checksum check, so no privileged action runs. Central (admin) requests are
 * untouched — tenancy is not initialised there.
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
            $memo = is_array($decoded) && is_array($decoded['memo'] ?? null) ? $decoded['memo'] : [];
            $name = (string) ($memo['name'] ?? '');
            $path = (string) ($memo['path'] ?? '');

            $isSetupFlow = str_contains($name, 'change-password')
                || str_contains($path, 'change-password')
                || str_contains($path, 'multi-factor-authentication');

            if (! $isSetupFlow) {
                abort(403, 'You must change your temporary password before continuing.');
            }
        }

        return $next($request);
    }
}
