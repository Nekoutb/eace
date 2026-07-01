<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A phone / WhatsApp number the visitor can leave on the login-page chat, as an
 * alternative to email, so the team can reach an anonymous visitor after an
 * escalation. Central table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('requester_phone')->nullable()->after('requester_email');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('requester_phone');
        });
    }
};
