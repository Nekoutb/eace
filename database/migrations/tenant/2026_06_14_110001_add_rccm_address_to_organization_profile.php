<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The organisation's RCCM (trade register) and billing address — also checked
 * against the client mentions on each supplier invoice (CGI art. 150).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_profile', function (Blueprint $table) {
            $table->string('entity_rccm')->nullable()->after('entity_niu');
            $table->string('entity_address')->nullable()->after('entity_rccm');
        });
    }

    public function down(): void
    {
        Schema::table('organization_profile', function (Blueprint $table) {
            $table->dropColumn(['entity_rccm', 'entity_address']);
        });
    }
};
