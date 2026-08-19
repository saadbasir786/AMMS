<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('terminals_uuid_unique');
            $table->foreignId('organization_id')->constrained('organizations', 'id', 'term_org_fk')->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name', 180);
            $table->string('serial_number', 180)->nullable();
            $table->string('manufacturer', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('platform', 80)->nullable();
            $table->string('os_version', 80)->nullable();
            $table->string('app_version', 80)->nullable();
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('registered_by_user_id')->constrained('users', 'id', 'term_registered_user_fk')->restrictOnDelete();
            $table->timestamp('registered_at')->useCurrent();
            $table->foreignId('current_session_id')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'terminals_organization_code_unique');
            $table->index(['organization_id', 'status']);
            $table->index('current_session_id');
        });

        Schema::create('terminal_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('terminal_id')->constrained('terminals', 'id', 'tc_terminal_fk')->restrictOnDelete();
            $table->string('token_hash', 64)->unique('terminal_credentials_hash_unique');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users', 'id', 'tc_created_user_fk')->restrictOnDelete();
            $table->timestamps();
            $table->index(['terminal_id', 'revoked_at']);
            $table->index(['expires_at', 'revoked_at']);
        });

        Schema::create('meeting_terminal_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('terminal_assignments_uuid_unique');
            $table->foreignId('meeting_id')->constrained('meetings', 'id', 'mta_meeting_fk')->restrictOnDelete();
            $table->foreignId('terminal_id')->constrained('terminals', 'id', 'mta_terminal_fk')->restrictOnDelete();
            $table->foreignId('baseline_schedule_version_id')->constrained('meeting_schedule_versions', 'id', 'mta_schedule_fk')->restrictOnDelete();
            $table->foreignId('baseline_participant_version_id')->constrained('meeting_participant_versions', 'id', 'mta_participant_fk')->restrictOnDelete();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->string('status', 20)->default('ASSIGNED')->index();
            $table->foreignId('assigned_by_user_id')->constrained('users', 'id', 'mta_assigned_user_fk')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users', 'id', 'mta_revoked_user_fk')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
            $table->index(['meeting_id', 'status']);
            $table->index(['terminal_id', 'status', 'valid_from', 'valid_until'], 'terminal_assignment_window_idx');
            $table->index(['baseline_schedule_version_id', 'baseline_participant_version_id'], 'terminal_assignment_baseline_idx');
        });

        Schema::create('terminal_meeting_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('terminal_sessions_uuid_unique');
            $table->foreignId('assignment_id')->constrained('meeting_terminal_assignments', 'id', 'tms_assignment_fk')->restrictOnDelete();
            $table->foreignId('terminal_id')->constrained('terminals', 'id', 'tms_terminal_fk')->restrictOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings', 'id', 'tms_meeting_fk')->restrictOnDelete();
            $table->foreignId('schedule_version_id')->constrained('meeting_schedule_versions', 'id', 'tms_schedule_fk')->restrictOnDelete();
            $table->foreignId('participant_version_id')->constrained('meeting_participant_versions', 'id', 'tms_participant_fk')->restrictOnDelete();
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->string('session_token_hash', 64)->unique('terminal_sessions_token_hash_unique');
            $table->timestamp('activated_at');
            $table->timestamp('expires_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('close_reason')->nullable();
            $table->timestamps();
            $table->index(['terminal_id', 'status'], 'terminal_sessions_terminal_status_idx');
            $table->index(['assignment_id', 'status'], 'terminal_sessions_assignment_status_idx');
            $table->index(['meeting_id', 'status'], 'terminal_sessions_meeting_status_idx');
        });

        Schema::table('terminals', function (Blueprint $table): void {
            $table->foreign('current_session_id', 'terminals_current_session_fk')
                ->references('id')->on('terminal_meeting_sessions')->nullOnDelete();
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_terminal_session_id')->nullable()->after('active_participant_change_case_id');
            $table->index('current_terminal_session_id');
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->foreign('current_terminal_session_id', 'meetings_current_terminal_session_fk')
                ->references('id')->on('terminal_meeting_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropForeign('meetings_current_terminal_session_fk');
            $table->dropIndex(['current_terminal_session_id']);
            $table->dropColumn('current_terminal_session_id');
        });

        Schema::table('terminals', function (Blueprint $table): void {
            $table->dropForeign('terminals_current_session_fk');
        });

        Schema::dropIfExists('terminal_meeting_sessions');
        Schema::dropIfExists('meeting_terminal_assignments');
        Schema::dropIfExists('terminal_credentials');
        Schema::dropIfExists('terminals');
    }
};
