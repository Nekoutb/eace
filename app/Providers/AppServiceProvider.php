<?php

namespace App\Providers;

use App\Domain\Approval\FourCheckEvaluator;
use App\Domain\Invoice\InvoiceReader;
use App\Domain\Risk\RegistryResolver;
use App\Domain\Risk\RiskEngine;
use App\Http\Middleware\InitializeTenancyForLivewire;
use App\Models\TaxpayerFileExtraction;
use App\Services\Acf\AcfAuthenticityConnector;
use App\Services\Acf\FakeAcfAuthenticityConnector;
use App\Services\Acf\Harmony2AcfConnector;
use App\Services\Fiscalis\DgiFiscalisConnector;
use App\Services\Fiscalis\FakeFiscalisConnector;
use App\Services\Fiscalis\FiscalisConnector;
use App\Services\Invoice\AzureInvoiceReader;
use App\Services\Invoice\ClaudeInvoiceReader;
use App\Services\Invoice\FakeInvoiceReader;
use App\Services\Registry\FakeRegistryResolver;
use App\Services\Registry\TaxpayerFileResolver;
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
        // NIU resolution: the ingested DGI taxpayer file once an extraction
        // exists, else the fake resolver (demo data / tests). The file resolver
        // returns null for NIUs absent from the file — a real existence check.
        $this->app->bind(RegistryResolver::class, function () {
            try {
                if (TaxpayerFileExtraction::current() !== null) {
                    return new TaxpayerFileResolver;
                }
            } catch (\Throwable) {
                // taxpayer_file_extractions not migrated yet — fall back.
            }

            return new FakeRegistryResolver;
        });

        // RiskEngine has no zero-arg constructor; build it with the default rule set.
        $this->app->singleton(RiskEngine::class, fn () => RiskEngine::default());

        // The four-check approval evaluator (strict accept/reject).
        $this->app->singleton(FourCheckEvaluator::class, fn () => FourCheckEvaluator::default());

        // Invoice OCR: Anthropic Claude when its key is set, else Azure Document
        // Intelligence when configured, else the fake reader for the demo.
        $this->app->bind(InvoiceReader::class, function () {
            $anthropicKey = config('services.anthropic.key');
            if (filled($anthropicKey)) {
                return new ClaudeInvoiceReader((string) $anthropicKey, (string) config('services.anthropic.model'));
            }

            $endpoint = config('services.azure_di.endpoint');
            $azureKey = config('services.azure_di.key');
            if (filled($endpoint) && filled($azureKey)) {
                return new AzureInvoiceReader((string) $endpoint, (string) $azureKey);
            }

            return new FakeInvoiceReader;
        });

        // ACF authenticity (Harmony2): the live headless-browser connector when
        // Browsershot is enabled and Chromium is pinned, else a no-network fake
        // that always reports "unverified" (never fabricates an authentic verdict).
        $this->app->bind(AcfAuthenticityConnector::class, function () {
            $url = (string) config('services.harmony2.acf_url', 'https://fanr.harmony2.cm/authentification');
            $chrome = config('services.browsershot.chrome_path');

            if (config('services.browsershot.enabled') && filled($chrome)) {
                return new Harmony2AcfConnector(
                    url: $url,
                    chromePath: (string) $chrome,
                    nodeBinary: config('services.browsershot.node_path'),
                    timeoutSeconds: (int) config('services.browsershot.timeout', 90) + 60,
                );
            }

            return new FakeAcfAuthenticityConnector($url);
        });

        // Fiscalis presence (DGI national file): the live headless-browser
        // connector when Browsershot is enabled, else a no-network fake that
        // always reports "unverified".
        $this->app->bind(FiscalisConnector::class, function () {
            $url = (string) config('services.dgi.consultation_url');
            $chrome = config('services.browsershot.chrome_path');

            if (config('services.browsershot.enabled') && filled($chrome)) {
                return new DgiFiscalisConnector(
                    url: $url,
                    chromePath: (string) $chrome,
                    nodeBinary: config('services.browsershot.node_path'),
                    timeoutSeconds: (int) config('services.browsershot.timeout', 90) + 60,
                );
            }

            return new FakeFiscalisConnector($url);
        });
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
