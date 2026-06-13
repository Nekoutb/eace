<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central (landlord) table. The shared cache of the latest known state of each
 * NIU, so one tenant's lookup serves every other tenant and keeps the platform
 * a polite, low-volume client of the DGI Fiscalis portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niu_registry', function (Blueprint $table) {
            $table->id();
            $table->string('niu', 14)->unique();
            $table->char('entity_type', 1)->nullable();    // P | M
            $table->string('taxpayer_name')->nullable();   // raison sociale / nom
            $table->string('sigle')->nullable();
            $table->string('regime')->nullable();          // Réel · Simplifié · Libératoire · Droit Commun
            $table->string('centre')->nullable();          // CIME / CDI de rattachement
            $table->string('activity')->nullable();        // code + libellé NACE / NAEMA
            $table->string('fiscalis_status')->nullable(); // Actif · Suspendu · Radié
            $table->boolean('on_active_file')->nullable(); // fichier des contribuables actifs (art. 143)
            $table->string('source')->default('fiscalis');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index('fiscalis_status');
            $table->index('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niu_registry');
    }
};
