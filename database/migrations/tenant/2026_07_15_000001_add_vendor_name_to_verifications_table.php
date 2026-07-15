<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The vendor's name as established at verification time — from the DGI
 * taxpayer register (raison sociale), else the ACF's taxpayer name, else the
 * invoice's OCR'd supplier name. Persisted so the compliance register and the
 * settlement report can show WHO each NIU belongs to without re-querying the
 * central register.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->string('vendor_name')->nullable()->after('niu');
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dropColumn('vendor_name');
        });
    }
};
