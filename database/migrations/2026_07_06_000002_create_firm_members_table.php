<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central. A person on an accounting firm's team — the SINGLE identity that
 * authenticates the firm's staff. Credentials (password + one authenticator
 * secret) live here and are mirrored into each assigned sub-account's user row,
 * so a member signs in once, with one TOTP, and can hold several client
 * workspaces. role = power_user (manages the firm) or member (works assigned
 * accounts only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firm_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firm_id')->constrained('firms')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('app_authentication_secret')->nullable();          // one TOTP secret, mirrored to workspaces
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->string('role')->default('member');                       // power_user | member
            $table->boolean('must_change_password')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firm_members');
    }
};
