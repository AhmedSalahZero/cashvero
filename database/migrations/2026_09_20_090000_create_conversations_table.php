<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat / Support Messaging feature.
 * ------------------------------------------------------------------
 * One "conversations" table backs BOTH regular user-to-user chat and
 * "Super Message" support tickets — a support ticket is simply a
 * conversation with type = 'support' and a status. This avoids
 * duplicating the messages table, the real-time broadcasting logic,
 * and the Vue chat UI for what is otherwise the exact same feature
 * (two or more people exchanging timestamped text messages).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['direct', 'support'])->default('direct');
            // Only used for support tickets, e.g. "Can't upload invoices".
            $table->string('subject')->nullable();
            // Only used for support tickets: open -> in_progress -> resolved.
            $table->enum('status', ['open', 'in_progress', 'resolved'])->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            // Nullable: Super Admin tickets/chats are not tied to one company.
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
