<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Domain\Auth\UserRole;
use App\Models\OrganizationProfile;
use App\Models\Tenant;
use App\Models\TenantLogin;
use App\Models\User;
use App\Support\TempPassword;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Provisions a new client (tenant) end to end: creates the tenant — which
 * synchronously creates its isolated database and runs the tenant migrations
 * (see TenancyServiceProvider, shouldBeQueued(false)) — and seeds the first admin
 * user with a one-time temporary password.
 *
 * No subdomain: clients sign in at one shared address and are resolved to their
 * workspace by their account (the central login directory, kept in sync by the
 * User observer). The workspace id is derived from the organisation name. Any
 * failure leaves nothing behind: the committed tenant row and its .sqlite file
 * are cleaned up for a clean retry.
 */
final class ClientProvisioningService
{
    /**
     * Provision a workspace.
     *
     * Admin-provisioned (the default): a one-time temporary password is generated
     * and the first user is an Admin who must change it on first sign-in.
     * Self-service: pass $password (the user's own) + $role — no temp password and
     * no forced change. $allocatedSpaceMb is the storage quota for the workspace.
     */
    public function provision(
        string $name,
        string $adminName,
        string $adminEmail,
        int $allocatedSpaceMb = 1024,
        ?string $password = null,
        UserRole $role = UserRole::Admin,
    ): ProvisionedClient {
        $adminEmail = trim($adminEmail);

        // One sign-in email belongs to exactly one workspace — the shared login
        // resolves a user to their workspace through this directory.
        if (TenantLogin::query()->where('email', $adminEmail)->exists()) {
            throw ClientProvisioningException::emailTaken($adminEmail);
        }

        $id = $this->generateTenantId($name);

        // Admin flow generates a one-time password; self-service uses the user's own.
        $usesTemporary = $password === null;
        $effectivePassword = $password ?? TempPassword::generate();

        try {
            // Synchronous: the tenant DB exists and is migrated once this returns.
            $tenant = Tenant::create([
                'id' => $id,
                'name' => trim($name),
                'allocated_space_mb' => max(0, $allocatedSpaceMb),
            ]);

            $tenant->run(function () use ($adminName, $adminEmail, $effectivePassword, $role, $usesTemporary, $name): void {
                // The User observer registers this email → tenant in the central
                // login directory, so the admin can sign in at the shared address.
                User::query()->create([
                    'name' => trim($adminName),
                    'email' => $adminEmail,
                    'password' => Hash::make($effectivePassword),
                    'role' => $role,
                    'must_change_password' => $usesTemporary,
                ]);

                // Seed the buyer identity with the organisation name so the
                // workspace is usable immediately (the NIU/RCCM are filled later).
                OrganizationProfile::current()->update(['entity_name' => trim($name)]);
            });

            return new ProvisionedClient(
                tenant: $tenant,
                loginUrl: $this->loginUrl(),
                adminEmail: $adminEmail,
                temporaryPassword: $usesTemporary ? $effectivePassword : null,
            );
        } catch (Throwable $e) {
            $this->cleanup($id, $adminEmail);

            // A concurrent provision that lost the unique-email race should still
            // surface the friendly "email already in use" message.
            if ($this->isUniqueViolation($e)) {
                throw ClientProvisioningException::emailTaken($adminEmail);
            }

            throw $e;
        }
    }

    /** The shared sign-in URL every client uses (no per-client subdomain). */
    private function loginUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/app/login';
    }

    /**
     * A unique, path-safe workspace id from the organisation name. The id is used
     * in the tenant DB filename and the Wasabi key prefix, so it must be a clean
     * slug and avoid reserved labels.
     */
    private function generateTenantId(string $name): string
    {
        $base = Str::slug(trim($name));
        if (strlen($base) < 2) {
            $base = 'client';
        }
        if (in_array($base, $this->reserved(), true)) {
            $base = 'client-'.$base;
        }

        $id = $base;
        $n = 1;
        while (Tenant::find($id) !== null) {
            $id = $base.'-'.(++$n);
        }

        return $id;
    }

    /**
     * Remove every trace a failed provision may have left: the (possibly already
     * committed) tenant row, the orphaned tenant database file, and any directory
     * entry the observer may have written.
     */
    private function cleanup(string $id, string $adminEmail): void
    {
        try {
            Tenant::find($id)?->delete(); // delete() also fires DeleteDatabase
        } catch (Throwable) {
            // Fall through to best-effort row removal + file unlink.
        }

        try {
            Tenant::query()->whereKey($id)->delete();
        } catch (Throwable) {
            // ignore — the row may already be gone
        }

        TenantLogin::unlink($adminEmail, $id);

        // Backstop: make sure the database file is gone even if delete() didn't.
        $path = database_path(config('tenancy.database.prefix').$id.config('tenancy.database.suffix'));
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function isUniqueViolation(Throwable $e): bool
    {
        return $e instanceof QueryException
            && (str_contains($e->getMessage(), 'UNIQUE constraint failed')
                || in_array((string) $e->getCode(), ['23000', '23505'], true));
    }

    /**
     * Labels that must not become a workspace id (reserved routes / prefixes).
     *
     * @return list<string>
     */
    private function reserved(): array
    {
        return ['www', 'admin', 'app', 'api', 'mail', 'central', 'localhost'];
    }
}
