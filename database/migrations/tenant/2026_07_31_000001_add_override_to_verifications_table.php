<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An operator's justification for proceeding despite the system's rejection.
 * The machine verdict/approval are left untouched — this is an ADDITIVE audit
 * annotation: the evidence still shows the checks failed, alongside who decided
 * to proceed and why. Only meaningful on a rejected verification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->text('override_reason')->nullable()->after('manifest');
            $table->unsignedBigInteger('overridden_by')->nullable()->after('override_reason');
            $table->timestamp('overridden_at')->nullable()->after('overridden_by');
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dropColumn(['override_reason', 'overridden_by', 'overridden_at']);
        });
    }
};
