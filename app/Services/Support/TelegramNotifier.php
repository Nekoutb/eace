<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Domain\Support\MessageAuthor;
use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The Telegram side of the customer-service bridge. Pushes an escalated
 * conversation to the team group and remembers the message id so replies to it can
 * be routed back to the customer. Dormant (no-op) until a bot token + group chat id
 * are configured, exactly like the email rollout. Never throws — a Telegram outage
 * must not break an escalation.
 */
final class TelegramNotifier
{
    public function enabled(): bool
    {
        return filled(config('services.telegram.bot_token'));
    }

    /** Send a message to a chat; returns the Telegram message_id, or null on failure. */
    public function sendMessage(string|int $chatId, string $text, ?int $replyToMessageId = null): ?int
    {
        if (! $this->enabled()) {
            return null;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if ($replyToMessageId !== null) {
            $payload['reply_to_message_id'] = $replyToMessageId;
        }

        $result = $this->call('sendMessage', $payload);

        return ($result['ok'] ?? false) === true ? (int) ($result['result']['message_id'] ?? 0) : null;
    }

    /** Push an escalated conversation to the team group + remember the message for reply matching. */
    public function pushEscalation(Conversation $conversation): void
    {
        $chatId = config('services.telegram.chat_id');
        if (! $this->enabled() || blank($chatId)) {
            return;
        }

        $question = optional(
            $conversation->messages()->where('author', MessageAuthor::Customer->value)->latest('id')->first()
        )->body ?? $conversation->subject ?? '';

        $messageId = $this->sendMessage((string) $chatId, $this->escalationText($conversation, (string) $question));

        if ($messageId !== null) {
            $conversation->forceFill(['telegram_message_id' => (string) $messageId])->save();
        }
    }

    /**
     * Surface a new customer message in an ALREADY-escalated thread to the team —
     * threaded under the existing card (a reply to it), and advance the reply anchor
     * so a reply to this latest note still routes back. Avoids spamming new cards.
     */
    public function pushFollowUp(Conversation $conversation, string $customerMessage): void
    {
        $chatId = config('services.telegram.chat_id');
        if (! $this->enabled() || blank($chatId) || blank($conversation->telegram_message_id)) {
            return;
        }

        $e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $who = $conversation->requester_name ?: ($conversation->requester_email ?: '—');

        $text = '📩 <b>'.(string) __('support.telegram.follow_up').'</b> — '.$e($who)."\n\n"
            .'<i>'.$e(Str::limit($customerMessage, 600)).'</i>';

        $messageId = $this->sendMessage((string) $chatId, $text, (int) $conversation->telegram_message_id);

        if ($messageId !== null) {
            $conversation->forceFill(['telegram_message_id' => (string) $messageId])->save();
        }
    }

    private function escalationText(Conversation $conversation, string $question): string
    {
        $e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $url = rtrim((string) config('app.url'), '/').'/admin/conversations';
        $who = $conversation->requester_name ?: ($conversation->requester_email ?: '—');

        return '🔺 <b>'.$e($conversation->nature->label()).'</b> — '.$e($conversation->tenant_name ?: $conversation->tenant_id)."\n"
            .'👤 '.$e($who)."\n\n"
            .'<i>'.$e(Str::limit($question, 600)).'</i>'."\n\n"
            .'↩️ '.(string) __('support.telegram.reply_hint')."\n"
            .'🔗 <a href="'.$e($url).'">'.(string) __('support.telegram.open_console').'</a>';
    }

    // --- Setup helpers (used by the telegram:setup command) -----------------

    /** @return array<string, mixed> */
    public function getMe(): array
    {
        return $this->call('getMe');
    }

    /** @return array<string, mixed> */
    public function getUpdates(): array
    {
        return $this->call('getUpdates');
    }

    /** @return array<string, mixed> */
    public function setWebhook(string $url, string $secret): array
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => true,
        ]);
    }

    /** @return array<string, mixed> */
    public function deleteWebhook(): array
    {
        return $this->call('deleteWebhook', ['drop_pending_updates' => false]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(string $method, array $params = []): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'error' => 'telegram not configured'];
        }

        try {
            $base = 'https://api.telegram.org/bot'.config('services.telegram.bot_token');

            return (array) Http::timeout(15)->asJson()->post($base.'/'.$method, $params)->json();
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
