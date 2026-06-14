<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant table. Records the four-check approval alongside the existing graded
 * verdict: which stage the verification ran at, the strict accept/reject, the
 * four individual check results, and an optional link to the ACF used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->string('stage')->default('recording')->after('third_party_id');
            $table->string('approval')->nullable()->after('verdict');
            $table->json('checks')->nullable()->after('approval');
            $table->foreignId('acf_certificate_id')->nullable()->after('checks')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acf_certificate_id');
            $table->dropColumn(['stage', 'approval', 'checks']);
        });
    }
};
