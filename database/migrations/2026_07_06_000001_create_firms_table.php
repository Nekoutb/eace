<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central. The umbrella account for an accounting firm: one firm owns many
 * sub-accounts (client workspaces) and many members (its team). Pure metadata —
 * no compliance evidence ever lives here; that stays in each sub-account's own
 * tenant database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firms', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('max_sub_accounts')->default(25); // plan cap on client workspaces
            $table->unsignedInteger('default_quota_mb')->default(1024); // storage each new sub-account gets
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firms');
    }
};
