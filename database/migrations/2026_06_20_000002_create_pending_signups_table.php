<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central. A prospective customer's self-service sign-up, held here until they
 * confirm their email — only then is the real workspace (tenant database)
 * created. This keeps abandoned or fake sign-ups from ever provisioning a
 * database. Rows are consumed on verification and pruned once expired.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_signups', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 64)->unique();   // the verification-link secret
            $table->string('org_name');
            $table->string('name');                  // the prospective admin's name
            $table->string('email')->index();        // verified before provisioning
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_signups');
    }
};
