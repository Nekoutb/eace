<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the UI locale per request: an explicit session choice (set by the
 * language switcher) wins; otherwise the browser's preferred language on first
 * visit; otherwise the app default. Runs after StartSession in the panel stack.
 */
class SetLocale
{
    public const SUPPORTED = ['fr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $chosen = $request->hasSession() ? $request->session()->get('locale') : null;

        $locale = $chosen
            ?: $request->getPreferredLanguage(self::SUPPORTED)
            ?: config('app.locale');

        if (in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
