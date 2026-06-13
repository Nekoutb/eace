<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central (landlord) table. Immutable, append-only snapshots — the evidence
 * grade record of what Fiscalis returned at a given instant. SHA-256
 * content_hash chained via previous_hash makes tampering demonstrable, which is
 * what answers the "négligence fautive" case law: proof of diligence at a date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niu_registry_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('niu', 14)->index();
            $table->json('payload');                       // parsed fields at capture time
            $table->string('raw_html_path')->nullable();   // stored raw HTML (evidence)
            $table->string('screenshot_path')->nullable(); // rendered capture (evidence)
            $table->string('content_hash', 64);            // sha256 of canonical payload
            $table->string('previous_hash', 64)->nullable(); // chain to the prior snapshot of this NIU
            $table->string('source')->default('fiscalis');
            $table->timestamp('fetched_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['niu', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niu_registry_snapshots');
    }
};
