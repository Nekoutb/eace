<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central (landlord) table. One row per extraction of the DGI monthly taxpayer
 * file ("FICHIER <MOIS> AAAA.xlsx" from the consultation portal). Scheduled
 * weekly → ~52 rows a year. Each extraction is the audit reference behind the
 * NIU-existence check: which published file the verification was checked against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxpayer_file_extractions', function (Blueprint $table) {
            $table->id();
            $table->string('period_label');                 // 'MARS 2026'
            $table->string('source')->default('teledeclaration-dgi.cm');
            $table->string('source_url')->nullable();
            $table->string('file_path')->nullable();         // stored xlsx
            $table->string('file_hash', 64)->nullable();     // sha256
            $table->unsignedInteger('row_count')->default(0);
            $table->string('status')->default('pending');    // pending | completed | unchanged | failed
            $table->text('message')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();

            $table->index('period_label');
            $table->index('extracted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxpayer_file_extractions');
    }
};
