<?php

use App\Http\Controllers\SignUpController;
use App\Http\Middleware\InitializeTenancyForLivewire;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * Public self-service sign-up — a prospective customer provisions their own
 * isolated trial workspace (tenant + subdomain). Central web routes, so this is
 * not tenant-scoped; gated by config('niu.self_service_signup').
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
