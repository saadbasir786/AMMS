<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_minutes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('mm_uuid_uq');
            $table->foreignId('meeting_id')->unique('mm_meeting_uq')->constrained('meetings', 'id', 'mm_meeting_fk')->restrictOnDelete();
            $table->string('status')->default('DRAFT')->index('mm_status_idx');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users', 'id', 'mm_created_by_fk')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users', 'id', 'mm_finalized_by_fk')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('meeting_minute_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('mmv_uuid_uq');
            $table->foreignId('meeting_minutes_id')->constrained('meeting_minutes', 'id', 'mmv_minutes_fk')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('execution_session_id')->constrained('meeting_execution_sessions', 'id', 'mmv_execution_fk')->restrictOnDelete();
            $table->foreignId('schedule_version_id')->constrained('meeting_schedule_versions', 'id', 'mmv_schedule_fk')->restrictOnDelete();
            $table->foreignId('participant_version_id')->constrained('meeting_participant_versions', 'id', 'mmv_participant_fk')->restrictOnDelete();
            $table->text('summary');
            $table->text('general_notes')->nullable();
            $table->foreignId('prepared_by_user_id')->constrained('users', 'id', 'mmv_prepared_by_fk')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['meeting_minutes_id', 'version_number'], 'mmv_root_ver_uq');
        });

        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->foreign('current_version_id', 'mm_current_version_fk')->references('id')->on('meeting_minute_versions')->restrictOnDelete();
        });

        Schema::create('meeting_minute_version_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_minute_version_id')->constrained('meeting_minute_versions', 'id', 'mmvi_item_version_fk')->restrictOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->string('agenda_title', 500);
            $table->text('discussion_summary');
            $table->timestamps();
            $table->unique(['meeting_minute_version_id', 'sequence_number'], 'mmvi_seq_uq');
        });

        Schema::create('meeting_minute_version_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('mmvd_uuid_uq');
            $table->foreignId('meeting_minute_version_id')->constrained('meeting_minute_versions', 'id', 'mmvd_version_fk')->restrictOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->string('decision_number', 40);
            $table->text('decision_text');
            $table->foreignId('responsible_unit_id')->nullable()->constrained('organizational_units', 'id', 'mmvd_unit_fk')->restrictOnDelete();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees', 'id', 'mmvd_employee_fk')->restrictOnDelete();
            $table->date('target_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['meeting_minute_version_id', 'sequence_number'], 'mmvd_seq_uq');
            $table->unique(['meeting_minute_version_id', 'decision_number'], 'mmvd_num_uq');
        });

        Schema::create('meeting_minute_attendance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_minute_version_id')->constrained('meeting_minute_versions', 'id', 'mmas_version_fk')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees', 'id', 'mmas_employee_fk')->restrictOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units', 'id', 'mmas_unit_fk')->restrictOnDelete();
            $table->string('participant_role')->nullable();
            $table->string('display_name');
            $table->string('attendance_status', 20);
            $table->timestamps();
            $table->unique(['meeting_minute_version_id', 'employee_id'], 'mmas_employee_uq');
        });

        Schema::create('meeting_minute_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_minute_version_id')->constrained('meeting_minute_versions', 'id', 'mmr_version_fk')->restrictOnDelete();
            $table->string('reviewer_type', 30);
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units', 'id', 'mmr_unit_fk')->restrictOnDelete();
            $table->foreignId('authority_employee_id')->nullable()->constrained('employees', 'id', 'mmr_authority_fk')->restrictOnDelete();
            $table->string('status', 30)->default('PENDING');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users', 'id', 'mmr_reviewer_fk')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['meeting_minute_version_id', 'reviewer_type', 'organizational_unit_id', 'authority_employee_id'], 'mmr_identity_uq');
        });

        Schema::create('meeting_minute_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('mmd_uuid_uq');
            $table->foreignId('meeting_minutes_id')->unique('mmd_root_uq')->constrained('meeting_minutes', 'id', 'mmd_root_fk')->restrictOnDelete();
            $table->foreignId('meeting_minute_version_id')->unique('mmd_version_uq')->constrained('meeting_minute_versions', 'id', 'mmd_version_fk')->restrictOnDelete();
            $table->string('file_name');
            $table->string('mime_type', 120)->default('application/pdf');
            $table->string('content_sha256', 64);
            $table->timestamp('generated_at');
            $table->foreignId('generated_by_user_id')->constrained('users', 'id', 'mmd_generated_by_fk')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_minute_documents');
        Schema::dropIfExists('meeting_minute_reviews');
        Schema::dropIfExists('meeting_minute_attendance_snapshots');
        Schema::dropIfExists('meeting_minute_version_decisions');
        Schema::dropIfExists('meeting_minute_version_items');
        Schema::table('meeting_minutes', function (Blueprint $table): void { $table->dropForeign('mm_current_version_fk'); });
        Schema::dropIfExists('meeting_minute_versions');
        Schema::dropIfExists('meeting_minutes');
    }
};
