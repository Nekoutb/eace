<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant table. The tenant-side evidence artefacts of a verification (diligence
 * PDF, screenshot, raw HTML), each with a SHA-256 hash chained to the previous
 * one so the dossier is demonstrably untampered — the proof of "diligence
 * raisonnable" a tax audit demands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // pdf | screenshot | raw_html
            $table->string('path');
            $table->string('content_hash', 64);
            $table->string('previous_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('verification_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_files');
    }
};
