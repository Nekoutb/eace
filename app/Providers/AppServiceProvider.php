<?php

namespace App\Providers;

use App\Domain\Risk\RegistryResolver;
use App\Domain\Risk\RiskEngine;
use App\Services\Registry\FakeRegistryResolver;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
