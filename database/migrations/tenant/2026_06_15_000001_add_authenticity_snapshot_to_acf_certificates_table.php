<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 evidence: stores the path of the Harmony2 verdict screenshot captured
 * when an ACF's authenticity is confirmed against the live DGI portal, plus a
 * trimmed copy of the portal's verdict text for the audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acf_certificates', function (Blueprint $table) {
            $table->string('authenticity_snapshot_path')->nullable()->after('authenticity_url');
            $table->text('authenticity_note')->nullable()->after('authenticity_snapshot_path');
        });
    }

    public function down(): void
    {
        Schema::table('acf_certificates', function (Blueprint $table) {
            $table->dropColumn(['authenticity_snapshot_path', 'authenticity_note']);
        });
    }
};
