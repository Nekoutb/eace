<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central (landlord) table. Support questions raised by tenant staff from the
 * home-page chat box. They are stored centrally so the SaaS owner can read and
 * answer them from the admin panel, across every client workspace. The optional
 * response is surfaced back to the submitter in their own workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_queries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();    // which workspace it came from
            $table->string('tenant_name')->nullable();  // denormalised for the admin list
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->text('message');
            $table->text('response')->nullable();        // the owner's reply
            $table->string('status')->default('new');    // new | answered
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_queries');
    }
};
