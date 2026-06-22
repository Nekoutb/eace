<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the privacy-notice consent given at self-service sign-up (Loi 2024/017
 * art. 9): which version of the notice was accepted, and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_signups', function (Blueprint $table): void {
            $table->string('consent_version')->nullable()->after('email');
            $table->timestamp('consent_accepted_at')->nullable()->after('consent_version');
        });
    }

    public function down(): void
    {
        Schema::table('pending_signups', function (Blueprint $table): void {
            $table->dropColumn(['consent_version', 'consent_accepted_at']);
        });
    }
};
