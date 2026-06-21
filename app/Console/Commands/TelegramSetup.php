<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Support\TelegramNotifier;
use Illuminate\Console\Command;

/**
 * Inspect and configure the Telegram customer-service bridge.
 *
 *   telegram:setup info           show the bot + whether chat_id/secret are set
 *   telegram:setup updates        list chats the bot has seen (to find the group id)
 *   telegram:setup set-webhook    point Telegram at /telegram/webhook/<secret>
 *   telegram:setup delete-webhook stop the webhook (re-enables getUpdates)
 */
class TelegramSetup extends Command
{
    protected $signature = 'telegram:setup {action=info : info|updates|set-webhook|delete-webhook}';

    protected $description = 'Inspect and configure the Telegram customer-service bridge.';

    public function handle(TelegramNotifier $telegram): int
    {
        if (! $telegram->enabled()) {
            $this->error('TELEGRAM_BOT_TOKEN is not set.');

            return self::FAILURE;
        }

        return match ((string) $this->argument('action')) {
            'updates' => $this->updates($telegram),
            'set-webhook' => $this->setWebhook($telegram),
            'delete-webhook' => $this->render($telegram->deleteWebhook()),
            default => $this->info_($telegram),
        };
    }

    private function info_(TelegramNotifier $telegram): int
    {
        $me = $telegram->getMe();
        $this->line('Bot:                @'.($me['result']['username'] ?? '?'));
        $this->line('chat_id configured: '.(filled(config('services.telegram.chat_id')) ? (string) config('services.telegram.chat_id') : 'NO'));
        $this->line('webhook_secret set: '.(filled(config('services.telegram.webhook_secret')) ? 'yes' : 'NO'));

        return self::SUCCESS;
    }

    private function updates(TelegramNotifier $telegram): int
    {
        $result = $telegram->getUpdates();
        $seen = false;
        foreach ((array) ($result['result'] ?? []) as $update) {
            $chat = $update['message']['chat'] ?? null;
            if (is_array($chat)) {
                $seen = true;
                $this->line('chat_id='.($chat['id'] ?? '?').'  type='.($chat['type'] ?? '?').'  '.($chat['title'] ?? ($chat['username'] ?? '')));
            }
        }
        if (! $seen) {
            $this->warn('No chats yet. Add the bot to your group and send a message there, then re-run.');
        }

        return self::SUCCESS;
    }

    private function setWebhook(TelegramNotifier $telegram): int
    {
        $secret = (string) config('services.telegram.webhook_secret');
        if ($secret === '') {
            $this->error('Set TELEGRAM_WEBHOOK_SECRET first.');

            return self::FAILURE;
        }

        $url = rtrim((string) config('app.url'), '/').'/telegram/webhook/'.$secret;

        return $this->render($telegram->setWebhook($url, $secret), 'Webhook → '.$url);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function render(array $result, ?string $note = null): int
    {
        if ($note !== null) {
            $this->line($note);
        }
        $ok = ($result['ok'] ?? false) === true;
        $ok ? $this->info('OK') : $this->error('FAILED: '.json_encode($result));

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
