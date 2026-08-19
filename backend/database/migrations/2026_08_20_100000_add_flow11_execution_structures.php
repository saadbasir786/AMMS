<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_execution_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('mes_uuid_uq');
            $table->foreignId('meeting_id')->unique('mes_meeting_uq')->constrained('meetings')->restrictOnDelete();
            $table->foreignId('schedule_version_id')->constrained('meeting_schedule_versions')->restrictOnDelete();
            $table->foreignId('participant_version_id')->constrained('meeting_participant_versions')->restrictOnDelete();
            $table->string('status')->default('IN_PROGRESS')->index('mes_status_idx');
            $table->timestamp('started_at');
            $table->foreignId('started_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('start_notes')->nullable();
            $table->text('end_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_execution_sessions');
    }
};
