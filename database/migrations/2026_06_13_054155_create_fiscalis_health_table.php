<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central (landlord) table. Tracks the health of the DGI Fiscalis portal so the
 * lookup queue can open a circuit breaker and fall back to "serve last
 * snapshot" during the outages the article explicitly warns about. One row per
 * health probe; the latest row is the current state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscalis_health', function (Blueprint $table) {
            $table->id();
            $table->string('status');                 // up | degraded | down
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscalis_health');
    }
};
