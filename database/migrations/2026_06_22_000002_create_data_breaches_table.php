<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal-data breach register (Loi 2024/017 art. 22): a central record of each
 * breach, the measures taken, and when the Authority and affected data subjects
 * were notified "without delay".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_breaches', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('detected_at');
            $table->text('description');
            $table->string('categories')->nullable();   // categories of data / subjects affected
            $table->string('volume')->nullable();        // approximate number affected
            $table->string('risk')->default('medium');   // low | medium | high
            $table->text('measures')->nullable();        // technical/organisational measures taken
            $table->timestamp('authority_notified_at')->nullable();
            $table->timestamp('subjects_notified_at')->nullable();
            $table->string('status')->default('open');   // open | contained | closed
            $table->string('reported_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_breaches');
    }
};
