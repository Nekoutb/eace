<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\MonitoringDigestMail;
use App\Models\MonitoredService;
use App\Services\Monitoring\UptimeChecker;
use App\Services\Support\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Daily operations check: pings every active service's health URL, then sends a
 * digest of upcoming/overdue renewals and any down sites to the team Telegram
 * (and to NIU_MONITORING_ALERT_EMAIL when set). Silent when everything is healthy.
 */
class CheckMonitoredServices extends Command
{
    protected $signature = 'monitoring:check';

    protected $description = 'Ping monitored services and report upcoming/overdue renewals + any down sites.';

    public function handle(UptimeChecker $checker): int
    {
        /** @var Collection<int, MonitoredService> $services */
        $services = MonitoredService::query()->where('is_active', true)->get();

        foreach ($services as $service) {
            if (filled($service->health_url)) {
                $checker->check($service); // updates the in-memory instance too
            }
        }

        $overdue = $services->filter(fn (MonitoredService $s): bool => $s->renewalStatus() === 'overdue')->values();
        $dueSoon = $services->filter(fn (MonitoredService $s): bool => $s->renewalStatus() === 'due_soon')->values();
        $down = $services->filter(fn (MonitoredService $s): bool => $s->last_status === 'down')->values();

        if ($overdue->isEmpty() && $dueSoon->isEmpty() && $down->isEmpty()) {
            $this->info('All monitored services healthy — nothing to report.');

            return self::SUCCESS;
        }

        $this->sendTelegram($overdue, $dueSoon, $down);
        $this->sendEmail($overdue, $dueSoon, $down);

        $this->info('Monitoring digest sent ('.$overdue->count().' overdue, '.$dueSoon->count().' due soon, '.$down->count().' down).');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, MonitoredService>  $overdue
     * @param  Collection<int, MonitoredService>  $dueSoon
     * @param  Collection<int, MonitoredService>  $down
     */
    private function sendTelegram(Collection $overdue, Collection $dueSoon, Collection $down): void
    {
        $chatId = config('services.telegram.chat_id');
        $notifier = app(TelegramNotifier::class);

        if (! $notifier->enabled() || blank($chatId)) {
            return;
        }

        $e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $lines = ['🔔 <b>'.$e((string) __('monitoring.digest_title')).'</b>'];

        if ($overdue->isNotEmpty()) {
            $lines[] = "\n🔴 <b>".$e((string) __('monitoring.overdue')).' ('.$overdue->count().')</b>';
            foreach ($overdue as $s) {
                $lines[] = '• '.$e($s->name).' — '.$e($this->renewalLine($s));
            }
        }
        if ($dueSoon->isNotEmpty()) {
            $lines[] = "\n🟠 <b>".$e((string) __('monitoring.due_soon')).' ('.$dueSoon->count().')</b>';
            foreach ($dueSoon as $s) {
                $lines[] = '• '.$e($s->name).' — '.$e($this->renewalLine($s));
            }
        }
        if ($down->isNotEmpty()) {
            $lines[] = "\n⚠️ <b>".$e((string) __('monitoring.sites_down')).' ('.$down->count().')</b>';
            foreach ($down as $s) {
                $lines[] = '• '.$e($s->name).' — '.$e((string) $s->health_url).' ('.$s->last_http_code.')';
            }
        }

        $lines[] = "\n🔗 ".$e(rtrim((string) config('app.url'), '/').'/admin');

        $notifier->sendMessage((string) $chatId, implode("\n", $lines));
    }

    /**
     * @param  Collection<int, MonitoredService>  $overdue
     * @param  Collection<int, MonitoredService>  $dueSoon
     * @param  Collection<int, MonitoredService>  $down
     */
    private function sendEmail(Collection $overdue, Collection $dueSoon, Collection $down): void
    {
        $to = config('niu.monitoring.alert_email');
        if (! is_string($to) || $to === '') {
            return;
        }

        try {
            Mail::to($to)->send(new MonitoringDigestMail(
                $overdue->map(fn (MonitoredService $s): array => $this->row($s))->all(),
                $dueSoon->map(fn (MonitoredService $s): array => $this->row($s))->all(),
                $down->map(fn (MonitoredService $s): array => $this->row($s))->all(),
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array{name: string, detail: string}
     */
    private function row(MonitoredService $s): array
    {
        $detail = $s->last_status === 'down' && filled($s->health_url)
            ? (string) $s->health_url.' (HTTP '.$s->last_http_code.')'
            : $this->renewalLine($s);

        return ['name' => trim($s->name.($s->provider ? ' — '.$s->provider : '')), 'detail' => $detail];
    }

    private function renewalLine(MonitoredService $s): string
    {
        $days = $s->daysToRenewal();
        $when = $days === null
            ? (string) __('monitoring.no_date')
            : ($days < 0 ? (string) __('monitoring.overdue_days', ['n' => abs($days)]) : (string) __('monitoring.in_days', ['n' => $days]));
        $date = $s->renews_at?->format('d/m/Y');
        $pay = $s->auto_pay ? (string) __('monitoring.autopay_on') : (string) __('monitoring.autopay_off');

        return trim($when.($date ? ' ('.$date.')' : '').' · '.$pay);
    }
}
