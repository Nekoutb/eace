<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Support\ConversationNature;
use App\Domain\Support\ConversationStatus;
use App\Domain\Support\MessageAuthor;
use App\Models\Conversation;
use App\Services\Support\SupportBotConnector;
use App\Services\Support\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * The login-page "Need help?" chat. An UNAUTHENTICATED visitor asks a question;
 * the AI bot answers in the box. Anything it can't resolve becomes a pre-login
 * inquiry (channel = login, no workspace) that the team sees in the console and the
 * Telegram group, with the visitor's email for an email follow-up.
 */
class HelpChatController
{
    public function ask(Request $request, SupportBotConnector $bot): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $message = trim((string) $data['message']);
        $email = filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null;

        // Continue this visitor's conversation (bound to their session) so the bot
        // answers follow-ups in context.
        $conversation = $this->resumeOrStart($request, $message, $email);

        if ($email !== null && blank($conversation->requester_email)) {
            $conversation->forceFill(['requester_email' => $email])->save();
        }

        $conversation->postMessage(MessageAuthor::Customer, $message);

        // Stand down only once a human has ACTUALLY replied — an escalated thread is
        // still waiting on the team, so the bot keeps helping in the meantime.
        $humanReplied = $conversation->messages()->where('author', MessageAuthor::Agent->value)->exists();

        if ($humanReplied) {
            return response()->json([
                'reply' => (string) __('support.bot.holding'),
                'escalated' => true,
                'email' => $conversation->requester_email,
            ]);
        }

        $alreadyEscalated = $conversation->status === ConversationStatus::Escalated;

        $context = $conversation->load('messages')->botContext();

        try {
            $reply = $bot->respond($context['question'], 'Login visitor', app()->getLocale(), $context['history']);
        } catch (Throwable $e) {
            report($e);
            $reply = null;
        }

        if ($reply !== null) {
            $conversation->forceFill(['nature' => $reply->nature])->save();
        }

        if ($reply !== null && filled($reply->reply)) {
            $conversation->postMessage(MessageAuthor::Bot, $reply->reply, (string) __('support.author.bot'));
        }

        // Already with a human — the reply above was interim help; keep it escalated
        // (don't downgrade it out of the queue or re-ping Telegram).
        if ($alreadyEscalated) {
            return response()->json([
                'reply' => $reply->reply ?? (string) __('support.bot.holding'),
                'escalated' => true,
                'email' => $conversation->requester_email,
            ]);
        }

        $resolved = $reply !== null && $reply->resolved && ! $reply->isComplaint;

        if ($resolved) {
            $conversation->forceFill(['bot_resolved' => true, 'status' => ConversationStatus::AwaitingCustomer])->save();
        } else {
            $conversation->escalate();
            app(TelegramNotifier::class)->pushEscalation($conversation);
        }

        return response()->json([
            'reply' => $reply->reply ?? (string) __('support.bot.holding'),
            'escalated' => ! $resolved,
            'email' => $conversation->requester_email,
        ]);
    }

    /** Resume the visitor's session-bound conversation, or start a new one. */
    private function resumeOrStart(Request $request, string $message, ?string $email): Conversation
    {
        $id = $request->session()->get('help_chat_conversation_id');

        if ($id !== null) {
            $existing = Conversation::query()
                ->whereKey($id)
                ->where('channel', 'login')
                ->whereNotIn('status', [ConversationStatus::Resolved, ConversationStatus::Closed])
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $conversation = Conversation::query()->create([
            'tenant_id' => null,
            'tenant_name' => (string) __('app.login_chat.visitor'),
            'requester_email' => $email,
            'subject' => Str::limit($message, 80),
            'nature' => ConversationNature::Question,
            'status' => ConversationStatus::New,
            'channel' => 'login',
            'last_activity_at' => now(),
        ]);

        $request->session()->put('help_chat_conversation_id', $conversation->id);

        return $conversation;
    }
}
