<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central (landlord) table. Customer-service conversations (ticket threads) raised
 * by workspaces and handled by support staff from the admin console. The timeline
 * columns are the source of the customer-experience KPIs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->string('tenant_name')->nullable();
            $table->string('requester_name')->nullable();
            $table->string('requester_email')->nullable();
            $table->string('subject')->nullable();
            $table->string('nature')->default('question');
            $table->string('status')->default('new');
            $table->string('priority')->default('normal');
            $table->string('channel')->default('app');   // app | telegram
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->boolean('bot_resolved')->default(false);
            $table->unsignedTinyInteger('csat_score')->nullable();   // post-resolution rating (later phase)
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('nature');
            $table->index('assigned_to');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
