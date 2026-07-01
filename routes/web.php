<?php

use App\Http\Controllers\AcademyController;
use App\Http\Controllers\HelpChatController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\SignUpController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\AcademyAuthenticate;
use App\Http\Middleware\ForceAcademyPasswordChange;
use App\Http\Middleware\InitializeTenancyForLivewire;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

/*
 * Root: the bare host is the CUSTOMER front door — most visitors typing the
 * domain are clients, so send them into the compliance application (/app). An
 * unauthenticated client lands on the /app sign-in; a signed-in client lands in
 * their workspace; the landlord (admin) is routed on to /admin by the auth step.
 * The operator reaches the admin console directly at /admin.
 */
Route::get('/', fn () => redirect('/app'))->name('home');

/*
 * Read-only tenant impersonation ("badge in"). The admin console's badge-in action
 * verifies the admin's own TOTP, stashes a short-lived session grant, and redirects
 * to `enter`, which performs the auth/tenancy swap in a clean request. `exit` (from
 * the read-only banner) restores the admin. Session-backed — no token in the URL.
 */
Route::get('/impersonation/enter', [ImpersonationController::class, 'enter'])->name('impersonation.enter');
Route::post('/impersonation/exit', [ImpersonationController::class, 'exit'])->name('impersonation.exit');

/*
 * Public "how we protect your data" security showcase — the certified stack
 * (Hostinger / Vultr / Wasabi), encryption, WORM immutability and retention.
 * Public + central; respects the FR/EN locale.
 */
Route::get('/security', fn () => view('security'))
    ->middleware(SetLocale::class)
    ->name('security');

/*
 * Public privacy notice (Loi N° 2024/017 art. 14 & 21): controller identity,
 * purposes, data categories, recipients, international transfers, retention,
 * data-subject rights and how to exercise them. Public + central; FR/EN aware.
 */
Route::get('/privacy', fn () => view('privacy'))
    ->middleware(SetLocale::class)
    ->name('privacy');

/*
 * Telegram bridge webhook (customer-service platform). A server-to-server POST
 * from Telegram — no session/CSRF (exempted in bootstrap/app.php); authenticated
 * by the secret in the path AND the X-Telegram-Bot-Api-Secret-Token header.
 */
Route::post('/telegram/webhook/{secret}', TelegramWebhookController::class)->name('telegram.webhook');

/*
 * Public, anonymous customer-experience survey. The link carries an encrypted
 * workspace token (no user identity), so responses segment by client but stay
 * anonymous. FR/EN aware.
 */
Route::middleware(SetLocale::class)->group(function () {
    Route::get('/survey', [SurveyController::class, 'show'])->name('survey.show');
    Route::post('/survey', [SurveyController::class, 'submit'])->name('survey.submit');
});

/*
 * The login-page "Need help?" chat: an unauthenticated visitor's question is
 * answered by the bot, and escalated to the team (+ Telegram) if it can't resolve.
 * Throttled to deter abuse of this public, unauthenticated endpoint.
 */
Route::post('/help/ask', [HelpChatController::class, 'ask'])
    ->middleware(['throttle:10,1', SetLocale::class])
    ->name('help.ask');

// The login-chat visitor polls for the team's later replies (they have no signed-in
// view). Session-bound to their own conversation; returns only agent replies.
Route::get('/help/poll', [HelpChatController::class, 'poll'])
    ->middleware(['throttle:90,1', SetLocale::class])
    ->name('help.poll');

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
 * Elite Advisors Academy — the internal sales-training platform at /academy.
 * Gated by the `academy` guard (email + password, NO two-factor); accounts are
 * created by the operator from the admin console (Academy access). The module is
 * served from resources/academy (outside the public root) so only a signed-in
 * staff member can reach it. The POST sign-in is throttled.
 */
Route::middleware(SetLocale::class)->prefix('academy')->group(function (): void {
    Route::get('/login', [AcademyController::class, 'showLogin'])->name('academy.login');
    Route::post('/login', [AcademyController::class, 'login'])->middleware('throttle:8,1')->name('academy.login.attempt');
    Route::post('/logout', [AcademyController::class, 'logout'])->name('academy.logout');

    Route::middleware(AcademyAuthenticate::class)->group(function (): void {
        // Reachable even while a one-time password is pending, so the member can comply.
        Route::get('/password', [AcademyController::class, 'showChangePassword'])->name('academy.password');
        Route::post('/password', [AcademyController::class, 'changePassword'])->name('academy.password.update');

        Route::middleware(ForceAcademyPasswordChange::class)->group(function (): void {
            Route::get('/', [AcademyController::class, 'index'])->name('academy.index');
            Route::get('/assets/{path}', [AcademyController::class, 'asset'])->where('path', '.*')->name('academy.asset');
        });
    });
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
