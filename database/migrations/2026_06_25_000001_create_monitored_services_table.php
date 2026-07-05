<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator-facing monitoring register: every subscription / infrastructure service
 * that keeps the websites running (hosting, domain, storage, email, API keys, SSL),
 * with its renewal date, amount, auto-pay flag and an optional health URL. Drives
 * the admin "Services & monitoring" dashboard, the renewal reminders, and the
 * lightweight uptime ping. Central (landlord) data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_services', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('provider')->nullable();
            $table->string('category')->default('other');
            $table->string('powers')->nullable();          // which website(s) / what it supports
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 8)->default('USD');
            $table->string('billing_cycle')->default('yearly'); // monthly | quarterly | yearly | one_off
            $table->date('renews_at')->nullable();
            $table->boolean('auto_pay')->default(false);
            $table->string('health_url')->nullable();       // optional URL to ping for uptime
            $table->string('last_status')->nullable();      // up | down
            $table->unsignedSmallInteger('last_http_code')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_services');
    }
};
