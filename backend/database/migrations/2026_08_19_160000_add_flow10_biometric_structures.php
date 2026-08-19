<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_biometric_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('employee_biometric_templates_uuid_unique');
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('modality', 30)->default('FINGERPRINT');
            $table->string('finger_position', 30);
            $table->string('template_vendor', 120);
            $table->string('template_format', 120);
            $table->string('algorithm_version', 120);
            $table->binary('template_data');
            $table->string('template_checksum', 64);
            $table->decimal('quality_score', 8, 3)->nullable();
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->foreignId('source_terminal_id')->nullable()->constrained('terminals')->restrictOnDelete();
            $table->unsignedBigInteger('enrollment_session_id')->nullable();
            $table->foreignId('enrolled_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('enrolled_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revoke_reason')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'finger_position', 'version_number'], 'employee_biometric_template_version_unique');
            $table->index(['employee_id', 'finger_position', 'status'], 'employee_biometric_template_active_idx');
            $table->index(['organization_id', 'status'], 'employee_biometric_template_org_status_idx');
        });

        Schema::create('biometric_enrollment_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('biometric_enrollment_sessions_uuid_unique');
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained('terminals')->restrictOnDelete();
            $table->foreignId('started_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('PENDING_CAPTURE')->index();
            $table->unsignedTinyInteger('required_finger_count')->default(2);
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['terminal_id', 'status'], 'biometric_enrollment_terminal_status_idx');
            $table->index(['employee_id', 'status'], 'biometric_enrollment_employee_status_idx');
            $table->index(['expires_at', 'status'], 'biometric_enrollment_expiry_idx');
        });

        Schema::table('employee_biometric_templates', function (Blueprint $table): void {
            $table->foreign('enrollment_session_id', 'employee_biometric_template_enrollment_session_fk')
                ->references('id')->on('biometric_enrollment_sessions')->nullOnDelete();
            $table->index('enrollment_session_id', 'employee_biometric_template_enrollment_session_idx');
        });

        Schema::create('meeting_attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('meeting_attendance_records_uuid_unique');
            $table->foreignId('meeting_id')->constrained('meetings')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('participant_version_id')->constrained('meeting_participant_versions')->restrictOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained('terminals')->restrictOnDelete();
            $table->foreignId('terminal_session_id')->nullable()->constrained('terminal_meeting_sessions')->restrictOnDelete();
            $table->string('attendance_method', 30);
            $table->string('finger_position', 30)->nullable();
            $table->foreignId('matched_template_id')->nullable()->constrained('employee_biometric_templates')->restrictOnDelete();
            $table->string('matcher_vendor', 120)->nullable();
            $table->string('matcher_algorithm_version', 120)->nullable();
            $table->decimal('match_score', 12, 5)->nullable();
            $table->string('threshold_reference', 160)->nullable();
            $table->string('candidate_set_checksum', 64)->nullable();
            $table->uuid('client_event_uuid');
            $table->timestamp('recorded_at_server');
            $table->timestamp('device_captured_at')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('manual_reason')->nullable();
            $table->timestamps();
            $table->unique(['meeting_id', 'employee_id'], 'meeting_attendance_meeting_employee_unique');
            $table->unique('client_event_uuid', 'meeting_attendance_client_event_unique');
            $table->index(['meeting_id', 'participant_version_id'], 'meeting_attendance_version_idx');
            $table->index(['terminal_session_id', 'recorded_at_server'], 'meeting_attendance_session_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendance_records');
        Schema::table('employee_biometric_templates', function (Blueprint $table): void {
            $table->dropForeign('employee_biometric_template_enrollment_session_fk');
            $table->dropIndex('employee_biometric_template_enrollment_session_idx');
        });
        Schema::dropIfExists('biometric_enrollment_sessions');
        Schema::dropIfExists('employee_biometric_templates');
    }
};
