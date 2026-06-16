<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Domain\Auth\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TempPassword;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Throwable;

/**
 * Provisions a new client (tenant) end to end: creates the tenant — which
 * synchronously creates its database and runs the tenant migrations (see
 * TenancyServiceProvider, shouldBeQueued(false)) — registers its subdomain, and
 * seeds the first admin user with a one-time temporary password.
 *
 * The whole sequence (including Tenant::create, which can throw from a failed
 * CreateDatabase/MigrateDatabase) is wrapped so that ANY failure leaves nothing
 * behind: the committed tenant row, its subdomain, and the .sqlite file are all
 * cleaned up, freeing the subdomain for a clean retry.
 */
final class ClientProvisioningService
{
    public function provision(string $name, string $subdomain, string $adminName, string $adminEmail): ProvisionedClient
    {
        $id = $this->normaliseSubdomain($subdomain);
        $domain = $id.'.'.$this->baseDomain();

        if (Tenant::find($id) !== null || Domain::query()->where('domain', $domain)->exists()) {
            throw ClientProvisioningException::subdomainTaken($id);
        }

        $temporaryPassword = TempPassword::generate();

        try {
            // Synchronous: the tenant DB exists and is migrated once this returns.
            $tenant = Tenant::create(['id' => $id, 'name' => trim($name)]);
            $tenant->domains()->create(['domain' => $domain]);

            $tenant->run(function () use ($adminName, $adminEmail, $temporaryPassword): void {
                User::query()->create([
                    'name' => trim($adminName),
                    'email' => trim($adminEmail),
                    'password' => Hash::make($temporaryPassword),
                    'role' => UserRole::Admin,
                    'must_change_password' => true,
                ]);
            });

            return new ProvisionedClient(
                tenant: $tenant,
                domain: $domain,
                adminEmail: trim($adminEmail),
                temporaryPassword: $temporaryPassword,
            );
        } catch (Throwable $e) {
            $this->cleanup($id);

            // A concurrent provision that lost the unique-constraint race should
            // still surface the friendly "subdomain taken" message.
            if ($this->isUniqueViolation($e)) {
                throw ClientProvisioningException::subdomainTaken($id);
            }

            throw $e;
        }
    }

    /**
     * Remove every trace a failed provision may have left: the (possibly already
     * committed) tenant row + its domain, and the orphaned tenant database file.
     */
    private function cleanup(string $id): void
    {
        // Delete the row + cascade the domain; delete() also fires DeleteDatabase.
        try {
            $tenant = Tenant::find($id);
            $tenant?->delete();
        } catch (Throwable) {
            // Fall through to best-effort row removal + file unlink.
        }

        try {
            Tenant::query()->whereKey($id)->delete();
        } catch (Throwable) {
            // ignore — the row may already be gone
        }

        // Always make sure the database file is gone, even if delete()/DeleteDatabase
        // didn't remove it — this is the orphan-file backstop.
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

    private function normaliseSubdomain(string $subdomain): string
    {
        $id = Str::slug(trim($subdomain));

        if ($id === '' || in_array($id, $this->reserved(), true)) {
            throw ClientProvisioningException::invalidSubdomain($subdomain);
        }

        return $id;
    }

    private function baseDomain(): string
    {
        return (string) config('niu.tenant_base_domain', 'localhost');
    }

    /**
     * Labels that must not become a tenant subdomain.
     *
     * @return list<string>
     */
    private function reserved(): array
    {
        return ['www', 'admin', 'app', 'api', 'mail', 'localhost'];
    }
}
