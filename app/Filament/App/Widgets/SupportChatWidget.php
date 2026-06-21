<?php

namespace App\Filament\App\Widgets;

use App\Domain\Support\ConversationNature;
use App\Domain\Support\ConversationStatus;
use App\Domain\Support\MessageAuthor;
use App\Models\Conversation;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The home-page chat box. Tenant staff send a question; it opens a customer-service
 * conversation stored centrally so the support team can triage and answer it from
 * the admin console. The sender sees their own recent conversations and the latest
 * reply, like a light thread.
 */
class SupportChatWidget extends Widget
{
    protected string $view = 'filament.app.widgets.support-chat';

    protected int|string|array $columnSpan = 'full';

    public ?string $message = null;

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

        $conversation->postMessage(MessageAuthor::Customer, $body, auth()->user()?->name);

        $this->message = null;

        Notification::make()->title(__('app.chat.notif_sent'))->success()->send();
    }
}
