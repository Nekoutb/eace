<?php

use App\Http\Controllers\SignUpController;
use App\Http\Middleware\InitializeTenancyForLivewire;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

/*
 * Root: the public front door. When self-service onboarding is open this is a
 * landing page where a prospective customer can create their own workspace or
 * sign in (the landlord reaches the admin console from the discreet link there).
 * When self-service is closed the bare host stays the landlord's front door.
 */
Route::get('/', fn () => config('niu.self_service_signup') ? view('landing') : redirect('/admin'))
    ->middleware(SetLocale::class)
    ->name('home');

/*
 * Public "how we protect your data" security showcase — the certified stack
 * (Hostinger / Vultr / Wasabi), encryption, WORM immutability and retention.
 * Public + central; respects the FR/EN locale.
 */
Route::get('/security', fn () => view('security'))
    ->middleware(SetLocale::class)
    ->name('security');

/*
 * Public, verify-first self-service onboarding. A prospective customer enters
 * their details, confirms their email, and only then is their isolated workspace
 * created with a one-time password emailed to them. Central web routes (not
 * tenant-scoped); gated by config('niu.self_service_signup'). The submitting
 * routes are throttled to deter abuse of the email-sending + provisioning path.
 */
Route::middleware(SetLocale::class)->group(function (): void {
    Route::get('/register', [SignUpController::class, 'show'])->name('signup');
    Route::post('/register', [SignUpController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/register/sent', [SignUpController::class, 'sent'])->name('signup.sent');
    Route::post('/register/resend', [SignUpController::class, 'resend'])->middleware('throttle:4,1')->name('signup.resend');
    Route::get('/onboarding/verify/{token}', [SignUpController::class, 'verify'])->name('signup.verify');
    Route::get('/onboarding/done', [SignUpController::class, 'done'])->name('signup.done');
});

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
