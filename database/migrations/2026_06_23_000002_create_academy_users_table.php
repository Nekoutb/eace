<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academy access accounts: the Elite Advisors staff who can sign in to the sales
 * training platform at /academy. Deliberately separate from the central admin
 * `users` table and from every tenant's users — an academy account grants access
 * to the training module ONLY (never the admin console or a workspace) and signs
 * in with a plain email + password (no two-factor). Central (landlord) data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_users');
    }
};
