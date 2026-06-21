<?php

namespace App\Livewire;

use App\Domain\Support\ConversationNature;
use App\Domain\Support\ConversationStatus;
use App\Domain\Support\MessageAuthor;
use App\Jobs\AnswerConversationWithBot;
use App\Models\Conversation;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * The in-app support chat — a floating bubble (bottom-right) on every signed-in
 * /app page, replacing the old dashboard card. A workspace user asks a question; it
 * opens a conversation, the bot answers (escalating what it can't resolve), and the
 * user sees the team's replies and rates resolved threads. Runs in the tenant +
 * auth context (Livewire updates go through InitializeTenancyForLivewire).
 */
class SupportChat extends Component
{
    public ?string $message = null;

    public ?int $conversationId = null;

    /**
     * Resume the customer's latest still-open conversation so the chat continues
     * across page loads instead of starting over each time.
     */
    public function mount(): void
    {
        $id = Conversation::query()
            ->where('tenant_id', (string) tenant()->getTenantKey())
            ->where('requester_email', auth()->user()?->email)
            ->whereIn('status', ConversationStatus::openValues())
            ->latest('id')
            ->value('id');

        $this->conversationId = $id !== null ? (int) $id : null;
    }

    /**
     * This user's recent conversations, newest first, with their messages loaded.
     *
     * @return Collection<int, Conversation>
     */
    public function recentConversations(): Collection
    {
        return Conversation::query()
            ->where('tenant_id', tenant()->getTenantKey())
            ->where('requester_email', auth()->user()?->email)
            ->with('messages')
            ->latest()
            ->limit(8)
            ->get();
    }

    public function send(): void
    {
        $this->validate(['message' => ['required', 'string', 'max:2000']]);

        $body = trim((string) $this->message);

        // Continue the ongoing conversation so the bot answers in context; only start
        // a fresh one if there's nothing open to continue.
        $conversation = $this->activeConversation() ?? $this->openConversation($body);

        $conversation->postMessage(MessageAuthor::Customer, $body, auth()->user()?->name);

        // Let the bot answer right away (with the thread as context); it escalates
        // anything it can't resolve and stands down once a human takes over.
        if (config('niu.support.bot_enabled')) {
            AnswerConversationWithBot::dispatchSync($conversation->id, app()->getLocale());
        }

        $this->message = null;

        Notification::make()->title(__('app.chat.notif_sent'))->success()->send();
    }

    /** The conversation this chat is continuing, if it's still open and belongs to this user. */
    private function activeConversation(): ?Conversation
    {
        if ($this->conversationId === null) {
            return null;
        }

        $conversation = Conversation::query()->find($this->conversationId);

        if ($conversation === null
            || $conversation->tenant_id !== (string) tenant()->getTenantKey()
            || $conversation->requester_email !== auth()->user()?->email
            || in_array($conversation->status, [ConversationStatus::Resolved, ConversationStatus::Closed], true)) {
            $this->conversationId = null;

            return null;
        }

        return $conversation;
    }

    private function openConversation(string $body): Conversation
    {
        $tenantKey = (string) tenant()->getTenantKey();

        $conversation = Conversation::query()->create([
            'tenant_id' => $tenantKey,
            'tenant_name' => tenant()->name ?? $tenantKey,
            'requester_name' => auth()->user()?->name,
            'requester_email' => auth()->user()?->email,
            'subject' => Str::limit($body, 80),
            'nature' => ConversationNature::Question,
            'status' => ConversationStatus::New,
            'channel' => 'app',
            'last_activity_at' => now(),
            'sla_due_at' => now()->addHours((int) config('niu.support.frt_target_hours', 8)),
        ]);

        $this->conversationId = $conversation->id;

        return $conversation;
    }

    /** Post-resolution CSAT: the customer rates a resolved conversation 👍/👎 (once). */
    public function rate(int $conversationId, int $score): void
    {
        $conversation = Conversation::query()
            ->whereKey($conversationId)
            ->where('tenant_id', tenant()->getTenantKey())
            ->where('requester_email', auth()->user()?->email)
            ->whereNull('csat_score')
            ->first();

        if ($conversation === null) {
            return;
        }

        $conversation->forceFill(['csat_score' => $score >= 1 ? 1 : 0])->save();

        Notification::make()->title(__('app.chat.csat_thanks'))->success()->send();
    }

    public function render(): View
    {
        return view('livewire.support-chat');
    }
}
