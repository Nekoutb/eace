<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central (landlord) table. The messages of a customer-service conversation —
 * from the customer, a support agent, or the AI bot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('author')->default('customer');   // customer | agent | bot
            $table->string('author_name')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
