<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central (landlord) table. The shared, cross-customer cache of ACF authenticity
 * verdicts: one row per ACF reference (the 620… identifier). An ACF's
 * authenticity is a global fact, so the first definitive Harmony2 verdict for a
 * reference — obtained by any workspace — is stored here and reused by every
 * other workspace that later checks the same certificate, instead of driving the
 * portal again. Validity/expiry is still computed locally per certificate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acf_authentications', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();       // the 620… ACF identifier
            $table->string('niu', 14)->nullable()->index();
            $table->string('authenticity_status');       // authentic | not_authentic
            $table->text('result_text')->nullable();
            $table->string('authenticity_url')->nullable();
            $table->string('snapshot_path')->nullable(); // canonical evidence on the central disk
            $table->timestamp('checked_at');             // when the portal was actually driven
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acf_authentications');
    }
};
