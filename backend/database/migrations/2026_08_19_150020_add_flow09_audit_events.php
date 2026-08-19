<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('terminal_id')->nullable()->constrained('terminals')->nullOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('meeting_terminal_assignments')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('terminal_meeting_sessions')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['terminal_id', 'created_at']);
            $table->index(['meeting_id', 'created_at']);
            $table->index(['assignment_id', 'created_at']);
            $table->index(['session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_audit_events');
    }
};
