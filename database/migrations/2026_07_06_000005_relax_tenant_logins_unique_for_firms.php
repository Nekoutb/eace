<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The login directory becomes MANY-to-one: one email may now resolve to several
 * workspaces (an accounting-firm member assigned to several client sub-accounts).
 * Drop the global-unique on email; make (email, tenant_id) the unique pair so a
 * given email still maps to any workspace at most once. Standalone clients are
 * unaffected — they simply have a single row, exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_logins', function (Blueprint $table): void {
            $table->dropUnique(['email']);            // was: one email -> one workspace
            $table->unique(['email', 'tenant_id']);   // now: one email -> many workspaces, each once
        });
    }

    public function down(): void
    {
        Schema::table('tenant_logins', function (Blueprint $table): void {
            $table->dropUnique(['email', 'tenant_id']);
            $table->unique(['email']);
        });
    }
};
