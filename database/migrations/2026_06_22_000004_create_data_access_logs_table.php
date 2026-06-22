<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read-level access log (Loi 2024/017 art. 27): an "a posteriori" trail of who
 * EXPORTED or DOWNLOADED personal data, and when — complementing the verification
 * audit trail (which already records who created/changed records).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_email')->nullable();
            $table->string('action');                    // export | download | view
            $table->string('resource');                  // e.g. "own-data", "evidence", "taxpayer-register"
            $table->string('context')->nullable();       // a free-text locator (id, ref, count)
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_access_logs');
    }
};
