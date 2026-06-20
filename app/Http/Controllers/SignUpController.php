<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Auth\UserRole;
use App\Services\Tenant\ClientProvisioningException;
use App\Services\Tenant\ClientProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Public self-service sign-up. A prospective customer creates their own isolated
 * trial workspace (a fresh tenant + subdomain), choosing their own password and
 * landing as its Administrator — they own the workspace, so they must be able to
 * manage its users and settings. Gated by config('niu.self_service_signup').
 */
class SignUpController
{
    public function show(): View
    {
        abort_unless((bool) config('niu.self_service_signup'), 404);

        return view('auth.signup');
    }

    public function register(Request $request, ClientProvisioningService $provisioning): RedirectResponse
    {
        abort_unless((bool) config('niu.self_service_signup'), 404);

        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $client = $provisioning->provision(
                name: $data['org_name'],
                adminName: $data['name'],
                adminEmail: $data['email'],
                allocatedSpaceMb: (int) config('niu.trial_allocated_space_mb', 250),
                password: $data['password'],
                role: UserRole::Admin,
            );
        } catch (ClientProvisioningException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['org_name' => (string) __('app.client.provision_error')]);
        }

        // Send them to the shared sign-in to log in with the password they chose.
        return redirect()->away($client->loginUrl.'?welcome=1');
    }
}
