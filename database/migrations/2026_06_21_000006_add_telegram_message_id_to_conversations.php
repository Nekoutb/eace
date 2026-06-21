<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The id of the Telegram message the bot posted to the team group when this
 * conversation was escalated. Replies to that message (matched by
 * reply_to_message.message_id) are routed straight back into the conversation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('conversations', 'telegram_message_id')) {
                $table->string('telegram_message_id')->nullable()->after('channel');
                $table->index('telegram_message_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'telegram_message_id')) {
                $table->dropIndex(['telegram_message_id']);
                $table->dropColumn('telegram_message_id');
            }
        });
    }
};
