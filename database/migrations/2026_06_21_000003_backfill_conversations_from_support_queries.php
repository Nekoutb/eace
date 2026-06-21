<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One-time backfill: turn every existing support_queries row (the old flat chat
 * box) into a conversation thread with its messages, so the new customer-service
 * console shows the full history. The old table is left intact as a safety copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_queries') || ! Schema::hasTable('conversations')) {
            return;
        }

        // Guard against a re-run leaving duplicates.
        if (DB::table('conversations')->exists()) {
            return;
        }

        foreach (DB::table('support_queries')->orderBy('id')->get() as $q) {
            $answered = ($q->status ?? 'new') === 'answered';
            $at = $q->answered_at ?? $q->updated_at ?? $q->created_at;

            $conversationId = DB::table('conversations')->insertGetId([
                'tenant_id' => $q->tenant_id,
                'tenant_name' => $q->tenant_name,
                'requester_name' => $q->user_name,
                'requester_email' => $q->user_email,
                'subject' => Str::limit((string) $q->message, 80),
                'nature' => 'question',
                'status' => $answered ? 'resolved' : 'new',
                'priority' => 'normal',
                'channel' => 'app',
                'first_response_at' => $answered ? $q->answered_at : null,
                'resolved_at' => $answered ? $q->answered_at : null,
                'last_activity_at' => $at,
                'created_at' => $q->created_at,
                'updated_at' => $q->updated_at,
            ]);

            DB::table('conversation_messages')->insert([
                'conversation_id' => $conversationId,
                'author' => 'customer',
                'author_name' => $q->user_name,
                'body' => $q->message,
                'created_at' => $q->created_at,
                'updated_at' => $q->created_at,
            ]);

            if ($answered && filled($q->response)) {
                DB::table('conversation_messages')->insert([
                    'conversation_id' => $conversationId,
                    'author' => 'agent',
                    'body' => (string) $q->response,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }
        }
    }

    public function down(): void
    {
        // One-way backfill; the conversations tables are dropped by their own migrations.
    }
};
