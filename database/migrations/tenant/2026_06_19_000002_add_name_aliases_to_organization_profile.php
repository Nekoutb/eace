<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accepted spellings of the organisation's own (buyer) name. An invoice may name
 * the buyer slightly differently — a trade name, the legal form abbreviated, an
 * old style — and that should not, on its own, fail the art. 150 client-identity
 * check. Admins maintain the list in Settings; it is consulted alongside
 * entity_name when matching the client identity printed on each invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_profile', function (Blueprint $table) {
            $table->json('entity_name_aliases')->nullable()->after('entity_name');
        });
    }

    public function down(): void
    {
        Schema::table('organization_profile', function (Blueprint $table) {
            $table->dropColumn('entity_name_aliases');
        });
    }
};
