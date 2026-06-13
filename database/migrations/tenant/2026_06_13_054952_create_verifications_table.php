<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant table. One row per NIU verification the tenant runs. snapshot_id points
 * at a central niu_registry_snapshots row (cross-database reference, so no FK):
 * the immutable evidence of what Fiscalis showed at the moment of checking —
 * which is exactly the "au moment de la facturation" condition of CGI art. 143.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('third_party_id')->nullable()->constrained()->nullOnDelete();
            $table->string('raw_input');                     // exactly what was submitted
            $table->string('niu', 14)->nullable()->index();  // parsed NIU, null when format invalid
            $table->uuid('snapshot_id')->nullable(); // -> central niu_registry_snapshots.id
            $table->string('verdict');               // Verdict value
            $table->json('triggered_rules')->nullable(); // [{rule, article, exposure_xaf, message}]
            $table->unsignedBigInteger('exposure_xaf')->default(0); // estimated exposure, XAF (no decimals)
            $table->foreignId('checked_by')->nullable(); // tenant user id
            $table->timestamps();

            $table->index('verdict');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};
