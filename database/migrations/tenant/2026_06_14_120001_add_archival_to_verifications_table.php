<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retention: after export, a verification's bulky evidence (the scanned PDFs) is
 * purged to free space, but a compact tamper-proof manifest (facts + SHA-256
 * hashes) is kept so the diligence can still be proven. invoice_file_path lets
 * the purge find and remove the stored invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->string('invoice_file_path')->nullable()->after('invoice_checks');
            $table->json('manifest')->nullable()->after('invoice_file_path');
            $table->timestamp('archived_at')->nullable()->after('manifest');

            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dropColumn(['invoice_file_path', 'manifest', 'archived_at']);
        });
    }
};
