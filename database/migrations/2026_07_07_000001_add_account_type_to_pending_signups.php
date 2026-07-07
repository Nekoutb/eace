<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service onboarding asks WHO is signing up: a single company (today's flow)
 * or an accounting FIRM managing several clients. The choice is captured on the
 * pending sign-up and decides what the email confirmation provisions: a standalone
 * workspace, or a firm umbrella + its own home workspace with the signer as
 * power user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_signups', function (Blueprint $table): void {
            $table->string('account_type')->default('company')->after('email'); // company | firm
        });
    }

    public function down(): void
    {
        Schema::table('pending_signups', function (Blueprint $table): void {
            $table->dropColumn('account_type');
        });
    }
};
