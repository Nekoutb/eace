<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant table. The tenant's directory of suppliers and clients. The NIU is
 * nullable — a counterparty may be onboarded before its NIU is known or while
 * it is still being chased.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('third_parties', function (Blueprint $table) {
            $table->id();
            $table->string('niu', 14)->nullable()->index();
            $table->string('name');
            $table->string('type')->default('supplier'); // supplier | client | both
            $table->string('rccm')->nullable();
            $table->string('bank_account_name')->nullable(); // for the "RIB at the exact name" check
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_strategic')->default(false); // re-verify monthly vs. yearly
            $table->text('notes')->nullable();
            $table->string('last_verdict')->nullable();      // cached latest Verdict value
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('third_parties');
    }
};
