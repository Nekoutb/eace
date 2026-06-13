<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds the CENTRAL database: an admin for the /admin panel and a demo
     * tenant. The demo tenant's own users/data are seeded by TenantDatabaseSeeder
     * via `php artisan tenants:seed`.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@niushield.cm'],
            ['name' => 'Administrateur NIU Shield', 'password' => Hash::make('password')],
        );

        if (! Tenant::query()->whereKey('demo')->exists()) {
            $tenant = Tenant::create(['id' => 'demo']);
            $tenant->domains()->create(['domain' => 'demo.localhost']);
        }
    }
}
