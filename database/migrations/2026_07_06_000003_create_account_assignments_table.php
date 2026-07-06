<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central. Which firm member may enter which sub-account (client workspace), and
 * in what capacity. This table is the source of truth for a member's workspace
 * switcher and for the login directory: a member sees ONLY the accounts listed
 * here. account_role scopes what they can do inside that one workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firm_member_id')->constrained('firm_members')->cascadeOnDelete();
            $table->string('tenant_id');
            $table->string('account_role')->default('admin'); // admin | recorder | settler (role inside that workspace)
            $table->unsignedBigInteger('assigned_by')->nullable(); // firm_member id who made the assignment
            $table->timestamps();

            $table->unique(['firm_member_id', 'tenant_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_assignments');
    }
};
