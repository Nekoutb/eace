# Access control — platform admin vs client portal

This document is the single, explicit statement of who may reach what. The rules
below are enforced in code and locked by `tests/Feature/PanelSeparationTest.php`
and `tests/Feature/CrossPanelRedirectHttpTest.php` — if a change breaks any of
them, CI fails.

## Two user populations, kept strictly apart

| Population | Lives in | Reaches | Examples |
|---|---|---|---|
| **Platform admin (central)** | the **central** `users` table (no `role` column) | **`/admin` only** | the landlord/operator, customer-service staff |
| **Client portal (tenant)** | each tenant's **own** `users` table (has a `role` column) | **`/app` only** | a client's Administrator, Invoice-recording, Invoice-settling users |

A third, fully separate population — **Academy** (sales-training) users — lives in
the central `academy_users` table behind its own `academy` guard and can reach
**only `/academy`**, never `/admin` or `/app`.

## The linchpin: `isCentralUser()`

`App\Models\User::isCentralUser()` returns `true` when the model has **no `role`
attribute** — i.e. it was loaded from the central users table (which has no
`role` column). Tenant users always carry a `role`, so they are never central.

> A test asserts the central `users` table has **no** `role` column and the
> tenant `users` table **does**. Never add a `role` column centrally — it would
> silently collapse the boundary.

## The five layers that stop a client reaching `/admin`

1. **Panel boundary** — `User::canAccessPanel()`: the `admin` panel requires
   `isCentralUser() === true`; the `app` panel requires `isCentralUser() === false`.
2. **Cross-panel middleware** — `App\Http\Middleware\Authenticate` (registered as
   each panel's `authMiddleware`) redirects a signed-in user who lands on a panel
   they cannot access to the panel they *can* (never a hard 403). A client opening
   `/admin` is bounced to `/app`; the landlord opening `/app` is bounced to `/admin`.
3. **Login resolution** — `/app/login` resolves the submitted email to a workspace
   via the central `tenant_logins` directory; a central user's email is not in it,
   so `/app/login` rejects them. `/admin` never initialises tenancy, so a tenant
   user's email is not found centrally and `/admin/login` rejects them.
4. **Tenancy DB isolation** — the `app` panel swaps the DB connection to the
   signed-in user's own workspace (`InitializeTenancyFromSession`, pinned at login).
   The `admin` panel never initialises tenancy. One tenant can never read another's
   data (`tests/Feature/TenantIsolationTest.php`).
5. **Resource guards** — every `/admin` resource declares
   `canAccess(): isCentralUser() === true`; every client-portal resource declares
   `canAccess(): isCentralUser() === false`. The audience is stated on the resource
   itself, so the boundary holds even if an outer layer were bypassed.

## The landing page always goes to the client portal

The bare host (`/`) **always** redirects to `/app` (`routes/web.php`). The bare
domain is the customer front door: an unauthenticated visitor lands on the
`/app` sign-in; a signed-in client lands in their workspace; a signed-in landlord
is forwarded on to `/admin` by layer 2. The operator reaches the console directly
at `/admin`.

## Client-portal resources carrying an explicit guard

`AcfCertificateResource`, `AuditLogResource`, `TaxpayerFileResource`,
`VerificationResource` — each now declares `canAccess(): isCentralUser() === false`.
(They were always protected by layers 1–4; the explicit guard is defence in depth
and self-documentation.)

## If you add a new resource

- **Admin/operator resource** → put it under `app/Filament/Resources/...` and add
  `public static function canAccess(): bool { return auth()->user()?->isCentralUser() === true; }`.
- **Client-portal resource** → put it under `app/Filament/App/Resources/...` and add
  `public static function canAccess(): bool { return auth()->user()?->isCentralUser() === false; }`.

Then extend `tests/Feature/PanelSeparationTest.php` so the new resource is covered.
