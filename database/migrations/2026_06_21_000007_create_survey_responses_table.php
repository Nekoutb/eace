<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central (landlord) table. Anonymous customer-experience survey responses: tagged
 * by workspace (so the operator can segment by client) but NOT by individual user.
 * Scores feed the satisfaction KPIs — CSAT, NPS, service quality, ease of use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();      // which workspace (no user link)
            $table->string('tenant_name')->nullable();
            $table->unsignedTinyInteger('service_quality')->nullable();  // 1-5
            $table->unsignedTinyInteger('ease_of_use')->nullable();      // 1-5
            $table->unsignedTinyInteger('responsiveness')->nullable();   // 1-5
            $table->unsignedTinyInteger('recommend')->nullable();        // 0-10 (NPS)
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
