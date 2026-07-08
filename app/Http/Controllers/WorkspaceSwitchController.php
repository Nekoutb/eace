<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\AccountSwitcher;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Switches a signed-in accounting-firm member between the client sub-accounts they
 * are assigned to. The current workspace is initialised from the session by the
 * tenancy middleware, so the acting member is resolved from their own workspace;
 * AccountSwitcher re-verifies the target against their assignments before swapping.
 * Always lands back on the /app home. A REFUSED switch (the account was deleted, or
 * access was revoked, since the page rendered) says so plainly — a silent bounce
 * reads as "the selector is broken".
 */
final class WorkspaceSwitchController
{
    public function __invoke(Request $request, ?string $tenant = null): RedirectResponse
    {
        // Route parameter first (cannot be dropped in transit); hidden-input body
        // field as fallback for older cached pages.
        $tenantId = trim((string) ($tenant ?? $request->input('tenant')));

        if ($tenantId === '') {
            // Observed live: a POST arriving with no target at all. Log what DID
            // arrive so the transport problem stays diagnosable.
            Log::warning('workspace switch arrived without a target', [
                'auth_id' => $request->user()?->getAuthIdentifier(),
                'content_type' => (string) $request->header('content-type'),
                'body_keys' => array_keys($request->except(['_token'])),
                'referer' => (string) $request->header('referer'),
            ]);
        }

        if ($tenantId !== '' && AccountSwitcher::switchTo($tenantId)) {
            return redirect('/app');
        }

        // Flashed to the session; the next Filament page render shows it.
        Notification::make()
            ->title((string) __('firm.switcher.unavailable_title'))
            ->body((string) __('firm.switcher.unavailable_body'))
            ->danger()
            ->persistent()
            ->send();

        return redirect('/app');
    }
}
