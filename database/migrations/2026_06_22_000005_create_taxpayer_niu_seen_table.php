<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ever seen" history of every NIU ingested since the first taxpayer-file load.
 * The live register (taxpayer_file_records) is replaced each run, so this is the
 * only memory of NIUs that have appeared before — it lets each refresh classify a
 * newly-added NIU as genuinely first-time vs a returning one that had dropped off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxpayer_niu_seen', function (Blueprint $table): void {
            $table->string('niu')->primary();
            $table->timestamp('first_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxpayer_niu_seen');
    }
};
