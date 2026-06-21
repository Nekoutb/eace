<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central (landlord) users only. Adds the customer-service staff_role (owner /
 * supervisor / agent) so support staff can be onboarded alongside the operator,
 * plus must_change_password so a freshly onboarded agent is forced to set their
 * own password on first sign-in (the tenant users table already has this column).
 * The existing operator account becomes the owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'staff_role')) {
                $table->string('staff_role')->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('staff_role');
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('must_change_password');
            }
        });

        // Every pre-existing central user is the SaaS operator → owner.
        DB::table('users')->whereNull('staff_role')->update(['staff_role' => 'owner']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['staff_role', 'must_change_password', 'last_login_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
