<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant table. At invoice recording the scanned invoice is checked against
 * CGI art. 150 (conditions de forme et de fond); we keep the extracted fields
 * and the per-condition findings alongside the verification, and the strict
 * approval already reflects them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->json('invoice_data')->nullable()->after('checks');
            $table->json('invoice_checks')->nullable()->after('invoice_data');
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dropColumn(['invoice_data', 'invoice_checks']);
        });
    }
};
