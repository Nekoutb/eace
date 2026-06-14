<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central (landlord) table. The current taxpayer file as ingested rows, one per
 * NIU — the authoritative offline source for the NIU-existence check (CGI art.
 * L 100). Replaced/refreshed by each extraction; the pointer records which one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxpayer_file_records', function (Blueprint $table) {
            $table->id();
            $table->string('niu', 20)->unique();
            $table->string('raison_sociale')->nullable();
            $table->string('sigle')->nullable();
            $table->string('activite')->nullable();
            $table->string('regime')->nullable();
            $table->string('cri')->nullable();
            $table->string('centre')->nullable();
            $table->unsignedBigInteger('taxpayer_file_extraction_id')->nullable();
            $table->timestamps();

            $table->index('taxpayer_file_extraction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxpayer_file_records');
    }
};
