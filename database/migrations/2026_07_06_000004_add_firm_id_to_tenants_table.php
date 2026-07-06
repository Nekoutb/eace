<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sub-account (client workspace) may belong to a firm. Null firm_id = a
 * standalone client (today's self-service / direct clients) — unchanged. A real
 * column (not stancl's data JSON) so the admin can group and roll up clients by
 * firm efficiently; App\Models\Tenant lists it in getCustomColumns().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->unsignedBigInteger('firm_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('firm_id');
        });
    }
};
