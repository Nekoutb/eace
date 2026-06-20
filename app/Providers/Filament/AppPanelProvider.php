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
use App\Models\User;
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
            // code on each sign-in, with one-time recovery codes. It is NOT
            // enforced while the user still carries a temporary password — they
            // must change it FIRST (ForcePasswordChange), then 2FA is required.
            // This avoids the change-password ↔ 2FA-setup deadlock on first login.
            ->multiFactorAuthentication(
                AppAuthentication::make()->recoverable(),
                isRequired: function (): bool {
                    if (! (bool) config('niu.two_factor_required', true)) {
                        return false;
                    }

                    // No user (route registration) keeps the requirement and the
                    // set-up route; a signed-in user is exempt only until they
                    // change their temporary password.
                    $user = auth()->user();

                    return ! ($user instanceof User && $user->must_change_password);
                },
            )
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
