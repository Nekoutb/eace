<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy users.role column default was 'operator', a value the role split
 * removed from the UserRole enum. Reset it to 'recorder' so a role-less insert
 * can never persist an invalid enum. Every application path sets the role
 * explicitly, but this keeps the schema and the enum consistent (e.g. a factory
 * or future code that omits the role lands on a valid value rather than crashing
 * on hydration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('recorder')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('operator')->change();
        });
    }
};
