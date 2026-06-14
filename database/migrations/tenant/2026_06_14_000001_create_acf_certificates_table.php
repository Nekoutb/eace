<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant table. Every ACF the platform processes is recorded here and monitored
 * for existence + validity over time (CGI art. L 94 bis). Settlement reads this
 * table to decide whether the recording-time ACF may be reused or payment must
 * block. Deduped on (reference, niu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acf_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('third_party_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->index();
            $table->string('niu', 14)->nullable()->index();
            $table->string('taxpayer_name')->nullable();
            $table->date('issued_at');
            $table->date('expires_at');
            $table->unsignedTinyInteger('validity_months')->default(3);
            $table->boolean('has_moratorium')->default(false);
            $table->string('regime')->nullable();
            $table->string('centre')->nullable();
            $table->string('status')->index();              // AcfStatus
            $table->string('authenticity_status')->nullable(); // AcfAuthenticityStatus
            $table->string('authenticity_url')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('file_path')->nullable();
            $table->json('raw_payload')->nullable();
            $table->foreignId('created_by')->nullable(); // tenant user id
            $table->timestamps();

            $table->unique(['reference', 'niu']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acf_certificates');
    }
};
