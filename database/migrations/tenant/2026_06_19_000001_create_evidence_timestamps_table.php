<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned. One row per evidence capture (a DGI/Harmony2 screenshot) holding
 * its SHA-256 and, once obtained, the independent RFC-3161 trusted-timestamp
 * token that seals the moment of capture. Keyed by the stored file so the UI and
 * the retry job can find a capture's seal by its path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_timestamps', function (Blueprint $table): void {
            $table->id();
            $table->string('disk')->default('local');
            $table->string('path');                    // the evidence image on the disk
            $table->string('sha256', 64);              // hash sealed by the TSA
            $table->string('algo')->default('sha256');
            // pending | signed | failed | disabled
            $table->string('status')->default('pending');
            $table->string('tsa_url')->nullable();
            $table->string('token_path')->nullable();  // the .tsr DER token on the disk
            $table->timestamp('timestamped_at')->nullable(); // TSA-asserted instant
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['disk', 'path']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_timestamps');
    }
};
