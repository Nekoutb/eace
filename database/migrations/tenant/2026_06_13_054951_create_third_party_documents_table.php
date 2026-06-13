<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant table. Compliance documents per counterparty (ACF, RCCM, CNPS, RIB).
 * expires_at drives the ACF 3-month validity countdown (CGI art. L 94).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('third_party_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('third_party_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // acf | rccm | cnps | rib | other
            $table->string('reference')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable(); // ACF: issued_at + 3 months
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->index(['third_party_id', 'type']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('third_party_documents');
    }
};
