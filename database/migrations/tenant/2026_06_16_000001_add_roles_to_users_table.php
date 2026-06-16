<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 onboarding: each tenant's staff gets a coarse role (admin manages the
 * organisation + its users; operator does the compliance work) plus a
 * must_change_password flag for the temp-password handover, and a last_login_at
 * stamp. Roles are per-tenant by construction — this column lives in the tenant
 * database, so one person can be an admin in one client and absent from another.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Default to the least-privileged role; provisioning explicitly
            // promotes the first user to admin.
            $table->string('role')->default('operator')->after('password');
            $table->boolean('must_change_password')->default(false)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'must_change_password', 'last_login_at']);
        });
    }
};
