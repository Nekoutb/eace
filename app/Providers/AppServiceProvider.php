<?php

namespace App\Providers;

use App\Domain\Approval\FourCheckEvaluator;
use App\Domain\Invoice\InvoiceReader;
use App\Domain\Risk\RegistryResolver;
use App\Domain\Risk\RiskEngine;
use App\Http\Middleware\EnforcePasswordRotation;
use App\Http\Middleware\InitializeTenancyForLivewire;
use App\Models\TaxpayerFileExtraction;
use App\Models\User;
use App\Services\Acf\AcfAuthenticityConnector;
use App\Services\Acf\AcfOcr;
use App\Services\Acf\ClaudeAcfOcr;
use App\Services\Acf\FakeAcfAuthenticityConnector;
use App\Services\Acf\FakeAcfOcr;
use App\Services\Acf\Harmony2AcfConnector;
use App\Services\Evidence\FakeTimestampAuthority;
use App\Services\Evidence\Rfc3161TimestampAuthority;
use App\Services\Evidence\TimestampAuthority;
use App\Services\Fiscalis\DgiFiscalisConnector;
use App\Services\Fiscalis\FakeFiscalisConnector;
use App\Services\Fiscalis\FiscalisConnector;
use App\Services\Invoice\AzureInvoiceReader;
use App\Services\Invoice\ClaudeInvoiceReader;
use App\Services\Invoice\FakeInvoiceReader;
use App\Services\Registry\DgiExportDownloader;
use App\Services\Registry\FakeRegistryResolver;
use App\Services\Registry\TaxpayerFileResolver;
use App\Services\Support\ClaudeSupportBot;
use App\Services\Support\FakeSupportBot;
use App\Services\Support\SupportBotConnector;
use App\Support\Impersonation;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Stancl\Tenancy\Events\TenancyInitialized;

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

        // Scanned-ACF OCR: Claude vision when the key is set (same engine as scanned
        // invoices), else a no-network fake — a scan then reads as "unreadable",
        // exactly the pre-OCR behaviour. Digital PDFs never reach this connector.
        $this->app->bind(AcfOcr::class, function () {
            $key = config('services.anthropic.key');
            if (filled($key)) {
                return new ClaudeAcfOcr((string) $key, (string) config('services.anthropic.model'));
            }

            return new FakeAcfOcr;
        });

        // Customer-service bot: live Claude when enabled AND a key is set, else the
        // no-network fake that always escalates to a human (never fabricates an answer).
        $this->app->bind(SupportBotConnector::class, function () {
            $key = config('services.anthropic.key');
            if (config('niu.support.bot_enabled') && filled($key)) {
                return new ClaudeSupportBot((string) $key, (string) config('niu.support.bot_model'));
            }

            return new FakeSupportBot;
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
                    timeoutSeconds: (int) config('services.browsershot.timeout', 90) + 150,
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
                    timeoutSeconds: (int) config('services.browsershot.timeout', 90) + 150,
                );
            }

            return new FakeFiscalisConnector($url);
        });

        // Full live taxpayer-list export: drives the DGI portal's "Exporter"
        // headlessly to download the ENTIRE register (~579k rows).
        $this->app->bind(DgiExportDownloader::class, fn () => new DgiExportDownloader(
            url: (string) config('services.dgi.export_url', 'https://teledeclaration-dgi.cm/modules/immatriculation/Consultation/listecontribuable.aspx'),
            chromePath: (string) config('services.browsershot.chrome_path'),
            nodeBinary: config('services.browsershot.node_path'),
            timeoutSeconds: (int) config('services.dgi.export_timeout', 420),
        ));

        // Trusted timestamping of evidence captures: the real RFC-3161 authority
        // when enabled (production), else a no-network fake so dev/tests never
        // reach a timestamp authority. The EvidenceTimestampService itself also
        // short-circuits to "disabled" when the feature is off.
        $this->app->bind(TimestampAuthority::class, function () {
            if (config('niu.timestamp.enabled')) {
                return new Rfc3161TimestampAuthority(
                    tsaUrl: (string) config('niu.timestamp.tsa_url'),
                    opensslBin: (string) config('niu.timestamp.openssl_bin', 'openssl'),
                    hashAlgo: (string) config('niu.timestamp.hash_algo', 'sha256'),
                    httpTimeout: (int) config('niu.timestamp.http_timeout', 12),
                );
            }

            return new FakeTimestampAuthority;
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
            ->middleware(['web', InitializeTenancyForLivewire::class, EnforcePasswordRotation::class]));

        // Stamp a tenant user's last sign-in. Guarded on the column being present
        // so central admin users (whose table has no last_login_at) are skipped.
        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;
            if ($user instanceof User && array_key_exists('last_login_at', $user->getAttributes())) {
                $user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        // Read-only impersonation: while an admin is badged into a workspace, block
        // every write to that tenant's database — the hard backstop beneath the
        // per-action gates. Fires for both page loads and Livewire requests (both
        // initialise tenancy). Central writes (audit) use a different connection and
        // are never guarded. No-op when no one is impersonating.
        Event::listen(TenancyInitialized::class, function (): void {
            Impersonation::guard(DB::connection());
        });
    }
}
