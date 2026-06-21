<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Support\ConversationStatus;
use App\Domain\Support\MessageAuthor;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives Telegram updates for the customer-service bridge. A team member who
 * REPLIES (in the group) to one of the bot's escalation messages has their reply
 * posted straight back into the matching conversation — and so to the customer
 * in-app. The endpoint is guarded by a secret in both the URL and Telegram's
 * X-Telegram-Bot-Api-Secret-Token header. It also opportunistically links a staff
 * member's numeric chat_id the first time they message the bot.
 */
class TelegramWebhookController
{
    public function __invoke(Request $request, string $secret): Response
    {
        $configured = (string) config('services.telegram.webhook_secret');

        if ($configured === ''
            || ! hash_equals($configured, $secret)
            || ! hash_equals($configured, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(403);
        }

        $message = $request->input('message');
        if (! is_array($message)) {
            return response('ok'); // ignore edits, joins, and other update types
        }

        $this->autoLinkAgent($message);

        $replyTo = $message['reply_to_message']['message_id'] ?? null;
        $text = trim((string) ($message['text'] ?? ''));

        if ($replyTo !== null && $text !== '') {
            $conversation = Conversation::query()->where('telegram_message_id', (string) $replyTo)->first();

            if ($conversation !== null) {
                $conversation->postMessage(MessageAuthor::Agent, $text, $this->authorName($message));

                if (in_array($conversation->status, [ConversationStatus::New, ConversationStatus::Open, ConversationStatus::Escalated], true)) {
                    $conversation->forceFill(['status' => ConversationStatus::AwaitingCustomer])->save();
                }
            }
        }

        return response('ok');
    }

    /**
     * Link a support agent's numeric chat_id the first time they message the bot —
     * matched by their captured @username — so escalations can later DM them.
     *
     * @param  array<string, mixed>  $message
     */
    private function autoLinkAgent(array $message): void
    {
        $from = $message['from'] ?? null;
        if (! is_array($from) || ! isset($from['username'], $from['id'])) {
            return;
        }

        User::query()
            ->whereRaw('lower(telegram_username) = ?', [mb_strtolower((string) $from['username'])])
            ->whereNull('telegram_chat_id')
            ->update(['telegram_chat_id' => (string) $from['id']]);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function authorName(array $message): string
    {
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $name = trim(((string) ($from['first_name'] ?? '')).' '.((string) ($from['last_name'] ?? '')));

        return $name !== '' ? $name : (string) ($from['username'] ?? __('support.author.agent'));
    }
}
