<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data-subject request register (Loi 2024/017 art. 37–46): access, rectification,
 * erasure, objection, portability. Raised by the signed-in customer's "My data"
 * page (or recorded by staff) and tracked to completion within the statutory window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->string('tenant_name')->nullable();
            $table->string('requester_email');
            $table->string('requester_name')->nullable();
            $table->string('type');                      // access | rectification | erasure | objection | portability
            $table->text('details')->nullable();
            $table->string('status')->default('new');    // new | in_progress | completed | refused
            $table->timestamp('due_at')->nullable();
            $table->string('handled_by')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');
    }
};
