<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\AccountSwitcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches a signed-in accounting-firm member between the client sub-accounts they
 * are assigned to. The current workspace is initialised from the session by the
 * tenancy middleware, so the acting member is resolved from their own workspace;
 * AccountSwitcher re-verifies the target against their assignments before swapping.
 * Always lands back on the /app home, freshly initialised to the chosen workspace.
 */
final class WorkspaceSwitchController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $tenantId = trim((string) $request->input('tenant'));

        if ($tenantId !== '') {
            AccountSwitcher::switchTo($tenantId); // no-op + no change if not assigned
        }

        return redirect('/app');
    }
}
