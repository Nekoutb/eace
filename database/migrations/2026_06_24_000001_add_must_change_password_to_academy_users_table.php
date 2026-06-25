<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A newly onboarded (or password-reset) Academy member receives a one-time
 * password by email and must set their own on first sign-in. Defaults to true so
 * any account created without an explicit password is force-changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('academy_users', function (Blueprint $table): void {
            $table->dropColumn('must_change_password');
        });
    }
};
