<?php

use App\Http\Middleware\InitializeTenancyForLivewire;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The Telegram webhook is a server-to-server POST (no session/CSRF token);
        // it is authenticated instead by a secret in the URL + the X-Telegram header.
        $middleware->validateCsrfTokens(except: [
            'telegram/webhook/*',
        ]);

        // CRITICAL under account-based tenancy: Laravel sorts route middleware by
        // priority and would otherwise run `auth` BEFORE the tenancy initializer —
        // the session's user id would then resolve against the CENTRAL users table
        // (the wrong or a missing user; the workspace switcher never worked live
        // because of this). The framework's default list is restated here with the
        // tenancy initializer pinned right after the session starts, before any
        // authentication. (prependToPriorityList can't do it: the default list
        // holds the AuthenticatesRequests INTERFACE, not the Authenticate class.)
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            InitializeTenancyForLivewire::class,
            AuthenticatesRequests::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesSessions::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);

        // Guests on an auth-gated plain route go to the customer sign-in (there is
        // no route named "login" in this app — without this, they got a 500).
        $middleware->redirectGuestsTo(fn () => '/app/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
