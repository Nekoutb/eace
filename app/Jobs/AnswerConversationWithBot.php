<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Support\ConversationStatus;
use App\Domain\Support\MessageAuthor;
use App\Models\Conversation;
use App\Services\Support\SupportBotConnector;
use App\Services\Support\TelegramNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * The AI auto-responder. For a brand-new customer conversation it asks the bot to
 * answer, records the bot's reply + classification, and either marks it
 * bot-resolved (awaiting the customer) or — for complaints, fiscal/legal matters,
 * or anything the bot can't resolve — escalates it to a human. Best-effort: any
 * failure leaves the conversation untouched (New) for a human to pick up.
 */
class AnswerConversationWithBot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $conversationId,
        public string $locale = 'en',
    ) {}

    public function handle(SupportBotConnector $bot): void
    {
        if (! config('niu.support.bot_enabled')) {
            return;
        }

        $conversation = Conversation::query()->with('messages')->find($this->conversationId);

        if ($conversation === null) {
            return;
        }

        // Stand down once a human has taken over, or the thread is escalated/closed —
        // but keep answering follow-ups while the bot is still the one handling it.
        $humanReplied = $conversation->messages->contains(
            fn ($m): bool => $m->author === MessageAuthor::Agent
        );

        if ($humanReplied || in_array($conversation->status, [
            ConversationStatus::Escalated,
            ConversationStatus::Resolved,
            ConversationStatus::Closed,
        ], true)) {
            return;
        }

        // Only answer when the customer's message is the latest (awaiting a reply).
        $latest = $conversation->messages->last();
        if ($latest === null || $latest->author !== MessageAuthor::Customer) {
            return;
        }

        $context = $conversation->botContext();

        if ($context['question'] === '') {
            return;
        }

        try {
            $reply = $bot->respond(
                $context['question'],
                (string) ($conversation->tenant_name ?? $conversation->tenant_id ?? ''),
                $this->locale,
                $context['history'],
            );
        } catch (Throwable $e) {
            report($e); // a bot/API failure must never lose the customer's question

            return;
        }

        $conversation->forceFill(['nature' => $reply->nature])->save();

        if (filled($reply->reply)) {
            $conversation->postMessage(MessageAuthor::Bot, $reply->reply, (string) __('support.author.bot'));
        }

        // Guardrail (defence in depth, also enforced in ClaudeSupportBot::map): a
        // complaint is NEVER auto-resolved, whatever the model returned.
        if ($reply->resolved && ! $reply->isComplaint) {
            $conversation->forceFill([
                'bot_resolved' => true,
                'status' => ConversationStatus::AwaitingCustomer,
            ])->save();

            return;
        }

        // Not resolved (complaint / fiscal-legal / uncertain) → hand to a human and
        // push it to the team's Telegram group (no-op until Telegram is configured).
        $conversation->escalate();
        app(TelegramNotifier::class)->pushEscalation($conversation);
    }
}
