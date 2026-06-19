<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The single 'operator' role is split into two single-stage roles: 'recorder'
 * (invoice recording) and 'settler' (invoice settlement). Existing operators did
 * the day-to-day compliance work, which starts at recording, so they are mapped
 * to 'recorder'; an admin can re-assign anyone to 'settler' afterwards. Runs
 * per-tenant via `php artisan tenants:migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'operator')->update(['role' => 'recorder']);
    }

    public function down(): void
    {
        // Collapse the split roles back to the legacy 'operator' value.
        DB::table('users')->whereIn('role', ['recorder', 'settler'])->update(['role' => 'operator']);
    }
};
