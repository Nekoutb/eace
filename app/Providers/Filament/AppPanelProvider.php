<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Widgets\ComplianceStats;
use App\Filament\Auth\Login;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\SetLocale;
use App\Support\BrandColors;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
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
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->path('app')
            ->login(Login::class)
            ->brandName('Elite Advisors Compliance Engine')
            ->brandLogo(fn () => new HtmlString(view('filament.brand-logo')->render()))
            ->brandLogoHeight('2.05rem')
            ->colors(BrandColors::palette())
            // Mandatory two-factor for every user: an authenticator-app (TOTP)
            // code on each sign-in, with one-time recovery codes.
            ->multiFactorAuthentication(AppAuthentication::make()->recoverable(), isRequired: (bool) config('niu.two_factor_required', true))
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('filament.locale-switcher')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.signup-link')->render(),
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
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
                // The app panel is tenant-only: identify the tenant by domain and
                // block access from the central domains (which the admin panel owns).
                InitializeTenancyByDomain::class,
                PreventAccessFromCentralDomains::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                ForcePasswordChange::class,
            ]);
    }
}
