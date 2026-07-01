<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Support\ConversationNature;
use App\Domain\Support\ConversationStatus;
use App\Domain\Support\MessageAuthor;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Support\SupportBotConnector;
use App\Services\Support\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * The login-page "Need help?" chat. An UNAUTHENTICATED visitor asks a question; the
 * AI bot answers in the box. Anything it can't resolve is escalated to the team —
 * but ONLY once the visitor has left an email or phone, since an anonymous visitor
 * has no other channel to receive a reply. Team replies are emailed to them (and, if
 * they keep the chat open, shown live via poll()).
 */
class HelpChatController
{
    public function ask(Request $request, SupportBotConnector $bot): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $message = trim((string) $data['message']);
        $email = filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null;
        $phone = filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null;

        $conversation = $this->resumeOrStart($request, $message, $email, $phone);

        // Capture any contact the visitor supplies — never overwrite once set.
        $contact = [];
        if ($email !== null && blank($conversation->requester_email)) {
            $contact['requester_email'] = $email;
        }
        if ($phone !== null && blank($conversation->requester_phone)) {
            $contact['requester_phone'] = $phone;
        }
        if ($contact !== []) {
            $conversation->forceFill($contact)->save();
        }

        $conversation->postMessage(MessageAuthor::Customer, $message);

        // Already with a human — the bot stands down; the visitor waits on the team.
        $humanReplied = $conversation->messages()->where('author', MessageAuthor::Agent->value)->exists();
        if ($humanReplied) {
            return response()->json(['reply' => (string) __('support.bot.holding'), 'escalated' => true]);
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

        $resolved = $reply !== null && $reply->resolved && ! $reply->isComplaint;
        $botText = $reply->reply ?? (string) __('support.bot.holding');

        // Follow-up on an already-escalated thread: surface it to the team, keep escalated.
        if ($alreadyEscalated) {
            if (! $resolved) {
                app(TelegramNotifier::class)->pushFollowUp($conversation, $context['question']);
            }

            return response()->json(['reply' => $botText, 'escalated' => true]);
        }

        if ($resolved) {
            $conversation->forceFill(['bot_resolved' => true, 'status' => ConversationStatus::AwaitingCustomer])->save();

            return response()->json(['reply' => $botText, 'escalated' => false]);
        }

        // Needs a human. An anonymous visitor can only be reached if they leave
        // contact details — so require an email OR phone BEFORE escalating.
        if (blank($conversation->requester_email) && blank($conversation->requester_phone)) {
            return response()->json([
                'reply' => $botText."\n\n".(string) __('support.login_chat.need_contact'),
                'escalated' => false,
                'needContact' => true,
            ]);
        }

        $conversation->escalate();
        app(TelegramNotifier::class)->pushEscalation($conversation);

        return response()->json([
            'reply' => $botText."\n\n".(string) __('support.login_chat.escalated_ack'),
            'escalated' => true,
        ]);
    }

    /**
     * Poll for team replies to this visitor's session-bound conversation, so a
     * visitor who keeps the chat open sees the answer live — they aren't signed in,
     * so there is no other in-page channel. Returns agent messages newer than `since`.
     */
    public function poll(Request $request): JsonResponse
    {
        $id = $request->session()->get('help_chat_conversation_id');
        $since = (int) $request->query('since', '0');

        if ($id === null) {
            return response()->json(['messages' => []]);
        }

        $conversation = Conversation::query()->whereKey($id)->where('channel', 'login')->first();
        if ($conversation === null) {
            return response()->json(['messages' => []]);
        }

        $messages = $conversation->messages()
            ->where('id', '>', $since)
            ->where('author', MessageAuthor::Agent->value)
            ->orderBy('id')
            ->get(['id', 'body']);

        return response()->json([
            'messages' => $messages->map(static fn (ConversationMessage $m): array => [
                'id' => $m->getKey(),
                'body' => (string) $m->body,
            ])->all(),
        ]);
    }

    /** Resume the visitor's session-bound conversation, or start a new one. */
    private function resumeOrStart(Request $request, string $message, ?string $email, ?string $phone): Conversation
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
            'requester_phone' => $phone,
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
