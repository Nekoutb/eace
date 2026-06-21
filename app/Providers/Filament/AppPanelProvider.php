<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Widgets\ComplianceStats;
use App\Filament\Auth\AppAuthentication;
use App\Filament\Auth\Login;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\InitializeTenancyFromSession;
use App\Http\Middleware\SetLocale;
use App\Support\BrandColors;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->path('app')
            ->viteTheme('resources/css/filament/app/theme.css')
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->brandName('Elite Advisors Compliance Engine')
            ->brandLogo(fn () => new HtmlString(view('filament.brand-logo')->render()))
            ->brandLogoHeight('2.05rem')
            ->colors(BrandColors::palette())
            // Mandatory two-factor for every user: an authenticator-app (TOTP)
            // code on each sign-in, with one-time recovery codes. On first login
            // the user enrols 2FA first, then changes their temporary password —
            // EnforcePasswordRotation lets both of those setup flows through.
            ->multiFactorAuthentication(AppAuthentication::make()->recoverable(), isRequired: (bool) config('niu.two_factor_required', true))
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('filament.locale-switcher')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.signup-link')->render(),
            )
            // Floating "Need help?" chat in the corner of the sign-in page — the bot
            // answers visitors who aren't signed in, escalating to the team if needed.
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.login-help-chat')->render(),
            )
            // The same floating chat, signed-in: on every workspace page (persisted
            // across SPA navigation). Replaces the old dashboard "Ask a question" card.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.app.support-chat-mount')->render(),
            )
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            ->widgets([
                ComplianceStats::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // Account-based tenancy (no subdomain): initialise the signed-in
                // user's workspace from the central session BEFORE the auth/session
                // middleware, so the user resolves against their tenant database.
                InitializeTenancyFromSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                ForcePasswordChange::class,
            ]);
    }
}
