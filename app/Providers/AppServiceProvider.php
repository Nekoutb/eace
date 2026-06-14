<?php

namespace App\Providers;

use App\Domain\Approval\FourCheckEvaluator;
use App\Domain\Risk\RegistryResolver;
use App\Domain\Risk\RiskEngine;
use App\Http\Middleware\InitializeTenancyForLivewire;
use App\Services\Registry\FakeRegistryResolver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The Fiscalis connector slots in here later by swapping the binding.
        $this->app->bind(RegistryResolver::class, FakeRegistryResolver::class);

        // RiskEngine has no zero-arg constructor; build it with the default rule set.
        $this->app->singleton(RiskEngine::class, fn () => RiskEngine::default());

        // The four-check approval evaluator (strict accept/reject).
        $this->app->singleton(FourCheckEvaluator::class, fn () => FourCheckEvaluator::default());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Make Livewire's shared update endpoint tenancy-aware so the app
        // (tenant) panel's actions — including login — run against the tenant
        // database, while the central admin panel keeps working on localhost.
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)
            ->middleware(['web', InitializeTenancyForLivewire::class]));
    }
}
