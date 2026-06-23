<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the taxpayer expiry-reminder emails sent from the Certificates screen:
 * when the most recent reminder went out and how many have been sent for this
 * ACF, so the UI can surface "reminded on …" and re-sends stay deliberate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acf_certificates', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('last_verified_at');
            $table->unsignedInteger('reminder_count')->default(0)->after('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('acf_certificates', function (Blueprint $table) {
            $table->dropColumn(['reminded_at', 'reminder_count']);
        });
    }
};
