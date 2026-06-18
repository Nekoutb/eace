<?php

declare(strict_types=1);

use App\Models\AcfCertificate;
use App\Models\Verification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

/*
 * Tenant web routes run inside an initialised tenant (domain-identified).
 * NIU Shield tenants reach the product through the Filament "app" panel
 * (path /app) and the versioned API, so no custom tenant web routes are
 * registered yet. Add tenant-scoped pages here as they appear.
 */
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Serves a stored evidence snapshot (the Harmony2 / DGI screenshot) to a
    // signed-in tenant user, so the Compliance Engine and evidence views can show
    // it via a normal <img> without inlining megabytes into the Livewire payload.
    // ?download=1 returns it as a file attachment.
    Route::get('/evidence-snapshot/{kind}/{id}', function (string $kind, int $id) {
        $disk = Storage::disk('local');
        $path = match ($kind) {
            'acf' => AcfCertificate::query()->whereKey($id)->value('authenticity_snapshot_path'),
            'fiscalis' => Verification::query()->whereKey($id)->value('fiscalis_snapshot_path'),
            default => null,
        };

        abort_unless(is_string($path) && $path !== '' && $disk->exists($path), 404);
        $absolute = $disk->path($path);

        return request()->boolean('download')
            ? response()->download($absolute, basename($path))
            : response()->file($absolute, ['Content-Type' => 'image/png']);
    })->middleware('auth')->name('evidence.snapshot');
});
