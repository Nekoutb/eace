<?php

declare(strict_types=1);

use App\Mail\MonitoringDigestMail;
use App\Models\MonitoredService;
use App\Services\Monitoring\UptimeChecker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

it('classifies renewal status by days remaining', function (int $days, string $expected): void {
    $service = new MonitoredService([
        'renews_at' => $days >= 0 ? now()->addDays($days) : now()->subDays(abs($days)),
    ]);

    expect($service->renewalStatus())->toBe($expected);
})->with([
    'far future' => [30, 'ok'],
    'just outside window' => [15, 'ok'],
    'edge of window' => [14, 'due_soon'],
    'a few days' => [3, 'due_soon'],
    'today' => [0, 'due_soon'],
    'overdue' => [-2, 'overdue'],
]);

it('reports a service with no renewal date as none', function (): void {
    expect((new MonitoredService)->renewalStatus())->toBe('none');
});

it('records a reachable service as up', function (): void {
    Http::fake(['https://ok.test/*' => Http::response('ok', 200)]);

    $service = MonitoredService::query()->create(['name' => 'Up', 'health_url' => 'https://ok.test/health']);
    app(UptimeChecker::class)->check($service);

    expect($service->last_status)->toBe('up')
        ->and($service->last_http_code)->toBe(200)
        ->and($service->last_checked_at)->not->toBeNull();
});

it('records an erroring service as down', function (): void {
    Http::fake(['https://bad.test/*' => Http::response('boom', 500)]);

    $service = MonitoredService::query()->create(['name' => 'Down', 'health_url' => 'https://bad.test/health']);
    app(UptimeChecker::class)->check($service);

    expect($service->last_status)->toBe('down')
        ->and($service->last_http_code)->toBe(500);
});

it('emails a digest when services need attention', function (): void {
    config(['niu.monitoring.alert_email' => 'ops@example.com', 'services.telegram.bot_token' => null]);
    Mail::fake();
    Http::fake(['https://down.test/*' => Http::response('', 503)]);

    MonitoredService::query()->create(['name' => 'Overdue', 'renews_at' => now()->subDays(3), 'is_active' => true]);
    MonitoredService::query()->create(['name' => 'Soon', 'renews_at' => now()->addDays(5), 'is_active' => true]);
    MonitoredService::query()->create(['name' => 'DownSite', 'health_url' => 'https://down.test/h', 'renews_at' => now()->addDays(90), 'is_active' => true]);

    $this->artisan('monitoring:check')->assertSuccessful();

    Mail::assertSent(MonitoringDigestMail::class, fn (MonitoringDigestMail $mail): bool => $mail->hasTo('ops@example.com')
        && count($mail->overdue) === 1
        && count($mail->dueSoon) === 1
        && count($mail->down) === 1);
});

it('sends nothing when every service is healthy', function (): void {
    config(['niu.monitoring.alert_email' => 'ops@example.com', 'services.telegram.bot_token' => null]);
    Mail::fake();

    MonitoredService::query()->create(['name' => 'Fine', 'renews_at' => now()->addDays(120), 'is_active' => true]);

    $this->artisan('monitoring:check')->assertSuccessful();

    Mail::assertNothingSent();
});
