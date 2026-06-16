<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 evidence: stores the live DGI "Fichier National des Contribuables"
 * consultation result for the verification's NIU — the presence verdict, the
 * verdict screenshot, and when it was captured. Presence on the national file
 * is the registration/active confirmation behind the four-check verdict.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->string('fiscalis_presence')->nullable()->after('triggered_rules');
            $table->string('fiscalis_snapshot_path')->nullable()->after('fiscalis_presence');
            $table->timestamp('fiscalis_checked_at')->nullable()->after('fiscalis_snapshot_path');
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dropColumn(['fiscalis_presence', 'fiscalis_snapshot_path', 'fiscalis_checked_at']);
        });
    }
};
