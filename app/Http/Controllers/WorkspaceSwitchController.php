<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\AccountSwitcher;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
    public function __invoke(Request $request): RedirectResponse
    {
        $tenantId = trim((string) $request->input('tenant'));

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
