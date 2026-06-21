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

        try {
            $reply = $bot->respond($message, 'Login visitor', app()->getLocale());
        } catch (Throwable $e) {
            report($e);
            $reply = null;
        }

        $conversation = Conversation::query()->create([
            'tenant_id' => null,
            'tenant_name' => (string) __('app.login_chat.visitor'),
            'requester_email' => $email,
            'subject' => Str::limit($message, 80),
            'nature' => $reply->nature ?? ConversationNature::Question,
            'status' => ConversationStatus::New,
            'channel' => 'login',
            'last_activity_at' => now(),
        ]);
        $conversation->postMessage(MessageAuthor::Customer, $message);

        if ($reply !== null && filled($reply->reply)) {
            $conversation->postMessage(MessageAuthor::Bot, $reply->reply, (string) __('support.author.bot'));
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
            'email' => $email,
        ]);
    }
}
