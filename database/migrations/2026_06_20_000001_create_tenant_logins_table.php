<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central. The login directory for account-based (no-subdomain) tenancy: it maps
 * each tenant user's sign-in email to the workspace (tenant) it belongs to, so a
 * client signing in at the shared address resolves to their own workspace without
 * a subdomain. Kept in sync from the tenant users (TenantLoginObserver) and the
 * provisioning flow. Email is globally unique — one login belongs to one
 * workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_logins', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('tenant_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_logins');
    }
};
