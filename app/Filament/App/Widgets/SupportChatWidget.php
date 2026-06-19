<?php

namespace App\Filament\App\Widgets;

use App\Models\SupportQuery;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * The home-page chat box. Tenant staff send a question; it is stored centrally
 * (SupportQuery) so the SaaS owner can answer it from the admin panel. The
 * sender sees their own recent questions and any reply, like a light thread.
 */
class SupportChatWidget extends Widget
{
    protected string $view = 'filament.app.widgets.support-chat';

    protected int|string|array $columnSpan = 'full';

    public ?string $message = null;

    /**
     * This user's recent questions and any replies (newest first).
     *
     * @return Collection<int, SupportQuery>
     */
    public function recentQueries(): Collection
    {
        return SupportQuery::query()
            ->where('tenant_id', tenant()->getTenantKey())
            ->where('user_email', auth()->user()?->email)
            ->latest()
            ->limit(8)
            ->get();
    }

    public function send(): void
    {
        $this->validate(['message' => ['required', 'string', 'max:2000']]);

        SupportQuery::query()->create([
            'tenant_id' => tenant()->getTenantKey(),
            'tenant_name' => tenant()->name ?? tenant()->getTenantKey(),
            'user_name' => auth()->user()?->name,
            'user_email' => auth()->user()?->email,
            'message' => trim((string) $this->message),
            'status' => 'new',
        ]);

        $this->message = null;

        Notification::make()->title(__('app.chat.notif_sent'))->success()->send();
    }
}
