<?php

use App\Http\Controllers\SignUpController;
use App\Http\Middleware\InitializeTenancyForLivewire;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

/*
 * Root: under account-based (no-subdomain) tenancy the bare host is the
 * landlord's front door, so send visitors to the admin console sign-in. Client
 * (tenant) users reach their own workspace through the dedicated /app/login link
 * they are given when onboarded; an already-signed-in user who lands on the wrong
 * panel is routed to theirs by RedirectToAccessiblePanel.
 */
Route::get('/', fn () => redirect('/admin'));

/*
 * Public "how we protect your data" security showcase — the certified stack
 * (Hostinger / Vultr / Wasabi), encryption, WORM immutability and retention.
 * Public + central; respects the FR/EN locale.
 */
Route::get('/security', fn () => view('security'))
    ->middleware(SetLocale::class)
    ->name('security');

/*
 * Public self-service sign-up — a prospective customer provisions their own
 * isolated trial workspace and signs in at the shared /app/login. Central web
 * routes, so this is not tenant-scoped; gated by config('niu.self_service_signup').
 */
Route::get('/register', [SignUpController::class, 'show'])->name('signup');
Route::post('/register', [SignUpController::class, 'register']);

/*
 * Language switch. Tenancy-aware so the locale lands in the same (tenant or
 * central) session the panels read. Links use a relative path so they stay on
 * the current host/subdomain.
 */
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, SetLocale::SUPPORTED, true)) {
        session(['locale' => $locale]);
    }

    return back();
})->middleware(InitializeTenancyForLivewire::class)->name('locale.switch');
