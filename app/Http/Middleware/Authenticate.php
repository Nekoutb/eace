<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Both panels share one host. Filament's stock Authenticate throws a hard 403
 * when a signed-in user opens a panel they cannot access — the landlord opening
 * /app, or a client opening /admin. Here we send them to the panel they CAN use
 * instead of a dead end. Unauthenticated visitors and the non-FilamentUser case
 * are handled exactly as Filament does.
 *
 * This has to live IN the auth step (it replaces Filament\Http\Middleware\
 * Authenticate in each panel's authMiddleware): Laravel's middleware priority
 * runs Authenticate before any later custom middleware could intercept the 403.
 */
class Authenticate extends FilamentAuthenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return; /** @phpstan-ignore-line */
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();

        $panel = Filament::getCurrentOrDefaultPanel();

        if ($user instanceof FilamentUser) {
            if (! $user->canAccessPanel($panel)) {
                throw new HttpResponseException(redirect($this->accessiblePanelUrl($user)));
            }

            return;
        }

        // Non-FilamentUser: Filament only permits this in local environments.
        abort_if(config('app.env') !== 'local', 403);
    }

    /** The home URL of the panel this user can actually reach (root as a fallback). */
    private function accessiblePanelUrl(FilamentUser $user): string
    {
        foreach (['app', 'admin'] as $id) {
            $panel = Filament::getPanel($id);
            $url = $panel->getUrl();

            if ($url !== null && $user->canAccessPanel($panel)) {
                return $url;
            }
        }

        return url('/');
    }
}
