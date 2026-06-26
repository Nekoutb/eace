<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Enters and leaves a read-only tenant impersonation. The badge-in action in the
 * admin console verifies the admin's TOTP and stashes a short-lived grant in the
 * session, then redirects here to perform the auth/tenancy swap in a clean (non-
 * Livewire) request. Exit restores the admin.
 */
class ImpersonationController extends Controller
{
    public function enter(Request $request): RedirectResponse
    {
        $grant = $request->session()->pull('impersonation_grant');
        $admin = Auth::user();

        $expires = is_array($grant) ? ($grant['expires'] ?? null) : null;

        if (! is_array($grant)
            || ! $admin instanceof User
            || ! $admin->canImpersonate()
            || ($grant['admin'] ?? null) !== $admin->getKey()
            || ! is_int($expires)
            || $expires < now()->timestamp) {
            abort(403);
        }

        $tenant = Tenant::find($grant['tenant'] ?? null);
        if (! $tenant instanceof Tenant || ! Impersonation::start($tenant)) {
            return redirect('/admin');
        }

        return redirect('/app');
    }

    public function exit(): RedirectResponse
    {
        Impersonation::stop();

        return redirect('/admin');
    }
}
