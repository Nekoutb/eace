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
 * Receives Telegram updates for the customer-service bridge. Any team member's
 * message in the group is posted straight back into a conversation — and so to the
 * customer in-app — with NO login required (group membership is the authorisation).
 * A REPLY to one of the bot's escalation messages routes precisely to that
 * conversation; a plain message routes to the most recent still-open escalation
 * (handy for the usual single active case — needs the bot's group privacy mode OFF
 * so it can see non-reply messages). The endpoint is guarded by a secret in both the
 * URL and Telegram's X-Telegram-Bot-Api-Secret-Token header, and it opportunistically
 * links a staff member's numeric chat_id the first time they post.
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

        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $text = trim((string) ($message['text'] ?? ''));

        // Only a human's plain text becomes a customer reply — skip the bot's own
        // posts, empty/non-text updates, and slash-commands.
        if ($text === '' || ($from['is_bot'] ?? false) === true || str_starts_with($text, '/')) {
            return response('ok');
        }

        $conversation = $this->targetConversation($message);

        if ($conversation !== null) {
            $conversation->postMessage(MessageAuthor::Agent, $text, $this->authorName($message));
            $conversation->emailRequesterReply($text); // login-chat visitors get it by email

            if (in_array($conversation->status, [
                ConversationStatus::New,
                ConversationStatus::Open,
                ConversationStatus::Escalated,
            ], true)) {
                $conversation->forceFill(['status' => ConversationStatus::AwaitingCustomer])->save();
            }
        }

        return response('ok');
    }

    /**
     * The conversation this group message answers: the one the agent REPLIED to
     * (precise), or — for a plain message — the most recent still-open conversation
     * that was escalated to the group, so "just type a reply" works for the usual
     * single active escalation.
     *
     * @param  array<string, mixed>  $message
     */
    private function targetConversation(array $message): ?Conversation
    {
        $replyTo = $message['reply_to_message']['message_id'] ?? null;

        if ($replyTo !== null) {
            return Conversation::query()->where('telegram_message_id', (string) $replyTo)->first();
        }

        return Conversation::query()
            ->whereNotNull('telegram_message_id')
            ->whereIn('status', ConversationStatus::openValues())
            ->orderByDesc('last_activity_at')
            ->first();
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
