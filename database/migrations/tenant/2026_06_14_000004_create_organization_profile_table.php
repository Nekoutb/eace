<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant's own identity as the buyer ("entity receiving the invoice"): the
 * name and NIU that every supplier invoice must correctly carry as the client
 * (CGI art. 150). A single row per tenant — the organisation's profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_profile', function (Blueprint $table) {
            $table->id();
            $table->string('entity_name')->nullable();
            $table->string('entity_niu')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_profile');
    }
};
