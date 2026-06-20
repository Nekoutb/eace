<?php

declare(strict_types=1);

use App\Http\Middleware\InitializeTenancyForLivewire;
use App\Models\Tenant;
use Illuminate\Http\Request;

/*
 * Livewire's shared /livewire/update route is what every panel POSTs to. Under
 * account-based (no-subdomain) tenancy the panel a request belongs to is decided
 * ONLY by the server-side session: a session carrying a workspace id is a tenant
 * session; one without is the landlord. These tests prove that decision is
 * authoritative and that nothing the caller controls (the snapshot memo path or
 * the Referer header) can move a signed-in tenant user into central context or
 * vice versa.
 */

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each->delete();
});

/** Build a Livewire update request carrying a populated session. */
function livewireRequest(array $params = [], ?string $tenantId = null): Request
{
    $request = Request::create('http://localhost/livewire/update', 'POST', $params);

    $session = app('session')->driver();
    if ($tenantId !== null) {
        $session->put('tenant_id', $tenantId);
    }
    $request->setLaravelSession($session);

    return $request;
}

/** Run the middleware and report the tenant id it left initialised. */
function tenantSeenBy(Request $request): ?string
{
    $seen = null;
    app(InitializeTenancyForLivewire::class)->handle($request, function () use (&$seen) {
        $seen = tenant('id');

        return response('ok');
    });

    return $seen;
}

it('initialises the signed-in workspace from the session', function () {
    Tenant::create(['id' => 'live']);

    expect(tenantSeenBy(livewireRequest(tenantId: 'live')))->toBe('live');
});

it('stays central when the session carries no workspace', function () {
    expect(tenantSeenBy(livewireRequest()))->toBeNull();
});

it('cannot be pushed into central context by a spoofed admin Referer', function () {
    // A signed-in tenant user crafts a request claiming to come from /admin.
    // The session still says they are a tenant — they must stay in their workspace.
    Tenant::create(['id' => 'victim']);

    $request = livewireRequest(tenantId: 'victim');
    $request->headers->set('referer', 'http://localhost/admin/clients');

    expect(tenantSeenBy($request))->toBe('victim');
});

it('cannot be pushed into central context by a spoofed admin snapshot path', function () {
    Tenant::create(['id' => 'victim2']);

    $request = livewireRequest([
        'components' => [['snapshot' => json_encode(['memo' => ['path' => 'admin/clients']])]],
    ], tenantId: 'victim2');

    expect(tenantSeenBy($request))->toBe('victim2');
});

it('stays central for a landlord session even when the request claims the app panel', function () {
    // No workspace in the session → landlord. A spoofed app snapshot/Referer must
    // not conjure a tenant out of nothing.
    $request = livewireRequest([
        'components' => [['snapshot' => json_encode(['memo' => ['path' => 'app/dashboard']])]],
    ]);
    $request->headers->set('referer', 'http://localhost/app/dashboard');

    expect(tenantSeenBy($request))->toBeNull();
});

it('drops a stale workspace pointer for a deleted tenant and stays central', function () {
    $request = livewireRequest(tenantId: 'gone');

    expect(tenantSeenBy($request))->toBeNull()
        ->and($request->session()->get('tenant_id'))->toBeNull();
});
