<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_exception_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('mec_uuid_uq');
            $table->foreignId('meeting_id')->constrained('meetings', 'id', 'mec_meeting_fk')->restrictOnDelete();
            $table->string('case_type', 32);
            $table->string('status', 32)->default('DRAFT');
            $table->string('governance_mode', 64);
            $table->string('baseline_meeting_status', 32);
            $table->unsignedBigInteger('baseline_schedule_version_id')->nullable();
            $table->unsignedBigInteger('baseline_participant_version_id')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users', 'id', 'mec_created_user_fk')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users', 'id', 'mec_applied_user_fk')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('withdrawn_by_user_id')->nullable()->constrained('users', 'id', 'mec_withdrawn_user_fk')->nullOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->unsignedTinyInteger('active_marker')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['meeting_id', 'active_marker'], 'mec_one_active_uq');
            $table->index(['meeting_id', 'status'], 'mec_meeting_status_idx');
            $table->index(['case_type', 'status'], 'mec_type_status_idx');
            $table->foreign('baseline_schedule_version_id', 'mec_baseline_schedule_fk')->references('id')->on('meeting_schedule_versions')->restrictOnDelete();
            $table->foreign('baseline_participant_version_id', 'mec_baseline_participant_fk')->references('id')->on('meeting_participant_versions')->restrictOnDelete();
        });

        Schema::create('meeting_exception_case_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('mecv_uuid_uq');
            $table->foreignId('exception_case_id')->constrained('meeting_exception_cases', 'id', 'mecv_case_fk')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('reason');
            $table->text('administrative_note')->nullable();
            $table->boolean('replacement_expected')->default(false);
            $table->date('proposed_resume_date')->nullable();
            $table->string('baseline_meeting_status', 32);
            $table->unsignedBigInteger('baseline_schedule_version_id')->nullable();
            $table->unsignedBigInteger('baseline_participant_version_id')->nullable();
            $table->foreignId('prepared_by_user_id')->constrained('users', 'id', 'mecv_prepared_user_fk')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['exception_case_id', 'version_number'], 'mecv_case_version_uq');
            $table->index(['exception_case_id', 'submitted_at'], 'mecv_case_submitted_idx');
            $table->foreign('baseline_schedule_version_id', 'mecv_baseline_schedule_fk')->references('id')->on('meeting_schedule_versions')->restrictOnDelete();
            $table->foreign('baseline_participant_version_id', 'mecv_baseline_participant_fk')->references('id')->on('meeting_participant_versions')->restrictOnDelete();
        });

        Schema::table('meeting_exception_cases', function (Blueprint $table): void {
            $table->foreign('current_version_id', 'mec_current_version_fk')->references('id')->on('meeting_exception_case_versions')->nullOnDelete();
        });

        Schema::create('meeting_exception_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('mer_uuid_uq');
            $table->foreignId('exception_case_version_id')->constrained('meeting_exception_case_versions', 'id', 'mer_version_fk')->restrictOnDelete();
            $table->string('reviewer_type', 32);
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units', 'id', 'mer_unit_fk')->nullOnDelete();
            $table->foreignId('authority_employee_id')->nullable()->constrained('employees', 'id', 'mer_authority_employee_fk')->nullOnDelete();
            $table->string('review_requirement', 32);
            $table->string('status', 32)->default('PENDING');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users', 'id', 'mer_reviewed_user_fk')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['exception_case_version_id', 'status'], 'mer_version_status_idx');
            $table->index(['reviewer_type', 'organizational_unit_id'], 'mer_reviewer_unit_idx');
            $table->index(['reviewer_type', 'authority_employee_id'], 'mer_reviewer_authority_idx');
        });

        Schema::create('meeting_attendance_invalidations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('meai_uuid_uq');
            $table->foreignId('meeting_attendance_record_id')->unique('mai_attendance_uq')->constrained('meeting_attendance_records', 'id', 'mai_attendance_fk')->restrictOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings', 'id', 'mai_meeting_fk')->restrictOnDelete();
            $table->foreignId('exception_case_id')->constrained('meeting_exception_cases', 'id', 'mai_case_fk')->restrictOnDelete();
            $table->string('reason', 64);
            $table->timestamp('invalidated_at')->useCurrent();
            $table->foreignId('invalidated_by_user_id')->nullable()->constrained('users', 'id', 'mai_invalidated_user_fk')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['meeting_id', 'reason'], 'mai_meeting_reason_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendance_invalidations');
        Schema::dropIfExists('meeting_exception_reviews');
        Schema::table('meeting_exception_cases', function (Blueprint $table): void {
            $table->dropForeign('mec_current_version_fk');
        });
        Schema::dropIfExists('meeting_exception_case_versions');
        Schema::dropIfExists('meeting_exception_cases');
    }
};
