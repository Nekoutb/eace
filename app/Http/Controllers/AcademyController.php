<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademyUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the Elite Advisors Academy (sales training) at /academy, behind the
 * `academy` guard (email + password, NO two-factor). The module lives OUTSIDE the
 * public root (resources/academy) and is streamed through here, so it is only ever
 * reachable by a signed-in staff member. Accounts are created by the operator from
 * the admin console (AcademyUserResource).
 */
final class AcademyController extends Controller
{
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::guard('academy')->check()) {
            return redirect()->route('academy.index');
        }

        return response()->view('academy.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // is_active is passed as a credential so the eloquent provider only matches
        // an enabled account; the AcademyAuthenticate middleware re-checks per request.
        $credentials = [
            'email' => mb_strtolower(trim($data['email'])),
            'password' => $data['password'],
            'is_active' => true,
        ];

        if (! Auth::guard('academy')->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => (string) __('academy.login_failed')]);
        }

        $request->session()->regenerate();

        $user = Auth::guard('academy')->user();
        if ($user !== null) {
            $user->forceFill(['last_login_at' => now()])->save();
        }

        return redirect()->intended(route('academy.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('academy')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('academy.login');
    }

    public function showChangePassword(): Response
    {
        return response()->view('academy.password');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = Auth::guard('academy')->user();
        if ($user instanceof AcademyUser) {
            $user->update([
                'password' => (string) $request->input('password'), // 'hashed' cast hashes it
                'must_change_password' => false,
            ]);
        }

        return redirect()->route('academy.index');
    }

    public function index(): Response
    {
        $html = (string) file_get_contents(resource_path('academy/index.html'));

        // The module references its screenshots relatively, so pin the base href to
        // /academy/, and inject a discreet sign-out control into its header.
        $base = '<base href="'.e(url('/academy')).'/">';
        $logout = '<form method="post" action="'.e(route('academy.logout')).'" style="display:flex;align-items:center;margin-left:10px">'
            .csrf_field()
            .'<button type="submit" style="border:1px solid #e7e6e1;background:#fff;border-radius:999px;padding:6px 13px;font-size:12px;font-weight:700;color:#6B6B6B;cursor:pointer">'
            .e(__('academy.sign_out'))
            .'</button></form>';

        $html = str_replace('<head>', '<head>'.$base, $html);
        $html = str_replace('</header>', $logout.'</header>', $html);

        return response($html);
    }

    public function asset(string $path): BinaryFileResponse
    {
        // Defend against path traversal; only serve real files under the assets dir.
        if (str_contains($path, '..')) {
            abort(404);
        }

        $full = resource_path('academy/assets/'.$path);
        if (! is_file($full)) {
            abort(404);
        }

        return response()->file($full);
    }
}
