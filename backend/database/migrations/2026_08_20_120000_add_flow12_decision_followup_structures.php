<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_decision_followups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('mdf_uuid_uq');
            $table->foreignId('meeting_id')->constrained('meetings', 'id', 'mdf_meeting_fk')->restrictOnDelete();
            $table->foreignId('meeting_minutes_id')->constrained('meeting_minutes', 'id', 'mdf_minutes_fk')->restrictOnDelete();
            $table->foreignId('meeting_minute_version_id')->constrained('meeting_minute_versions', 'id', 'mdf_version_fk')->restrictOnDelete();
            $table->foreignId('meeting_decision_id')->unique('mdf_decision_uq')->constrained('meeting_minute_version_decisions', 'id', 'mdf_decision_fk')->restrictOnDelete();
            $table->string('decision_number', 40);
            $table->string('status', 32)->default('UNCLASSIFIED')->index('mdf_status_idx');
            $table->foreignId('classified_by_user_id')->nullable()->constrained('users', 'id', 'mdf_classified_by_fk')->restrictOnDelete();
            $table->timestamp('classified_at')->nullable();
            $table->text('no_action_reason')->nullable();
            $table->timestamps();
            $table->index(['meeting_id', 'status'], 'mdf_meeting_status_idx');
            $table->index(['meeting_minute_version_id'], 'mdf_version_idx');
        });

        Schema::create('meeting_action_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('mai_uuid_uq');
            $table->foreignId('decision_followup_id')->constrained('meeting_decision_followups', 'id', 'mai_followup_fk')->restrictOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->foreignId('responsible_unit_id')->constrained('organizational_units', 'id', 'mai_unit_fk')->restrictOnDelete();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees', 'id', 'mai_employee_fk')->restrictOnDelete();
            $table->string('status', 32)->default('OPEN')->index('mai_status_idx');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->date('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_for_verification_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users', 'id', 'mai_submitted_by_fk')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users', 'id', 'mai_completed_by_fk')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users', 'id', 'mai_cancelled_by_fk')->restrictOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users', 'id', 'mai_created_by_fk')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->unique(['decision_followup_id', 'sequence_number'], 'mai_sequence_uq');
            $table->index(['responsible_unit_id', 'status'], 'mai_unit_status_idx');
            $table->index(['assigned_employee_id', 'status'], 'mai_employee_status_idx');
            $table->index(['due_date', 'status'], 'mai_due_status_idx');
        });

        Schema::create('meeting_action_item_updates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('maiu_uuid_uq');
            $table->foreignId('action_item_id')->constrained('meeting_action_items', 'id', 'maiu_item_fk')->restrictOnDelete();
            $table->string('update_type', 32);
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users', 'id', 'maiu_created_by_fk')->restrictOnDelete();
            $table->timestamps();
            $table->index(['action_item_id', 'created_at'], 'maiu_item_created_idx');
            $table->index(['action_item_id', 'update_type'], 'maiu_item_type_idx');
        });

        Schema::create('meeting_action_item_extension_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('maier_uuid_uq');
            $table->foreignId('action_item_id')->constrained('meeting_action_items', 'id', 'maier_item_fk')->restrictOnDelete();
            $table->date('current_due_date');
            $table->date('requested_due_date');
            $table->text('reason');
            $table->string('status', 20)->default('PENDING');
            $table->unsignedTinyInteger('pending_marker')->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users', 'id', 'maier_requested_by_fk')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users', 'id', 'maier_reviewed_by_fk')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestamps();
            $table->unique(['action_item_id', 'pending_marker'], 'maier_pending_uq');
            $table->index(['action_item_id', 'status'], 'maier_item_status_idx');
        });

        Schema::create('meeting_action_item_assignment_history', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('maiah_uuid_uq');
            $table->foreignId('action_item_id')->constrained('meeting_action_items', 'id', 'maiah_item_fk')->restrictOnDelete();
            $table->foreignId('from_unit_id')->nullable()->constrained('organizational_units', 'id', 'maiah_from_unit_fk')->restrictOnDelete();
            $table->foreignId('to_unit_id')->constrained('organizational_units', 'id', 'maiah_to_unit_fk')->restrictOnDelete();
            $table->foreignId('from_employee_id')->nullable()->constrained('employees', 'id', 'maiah_from_employee_fk')->restrictOnDelete();
            $table->foreignId('to_employee_id')->nullable()->constrained('employees', 'id', 'maiah_to_employee_fk')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('changed_by_user_id')->constrained('users', 'id', 'maiah_changed_by_fk')->restrictOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['action_item_id', 'changed_at'], 'maiah_item_changed_idx');
        });

        Schema::create('meeting_action_item_evidence', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('maie_uuid_uq');
            $table->foreignId('action_item_id')->constrained('meeting_action_items', 'id', 'maie_item_fk')->restrictOnDelete();
            $table->foreignId('action_item_update_id')->nullable()->constrained('meeting_action_item_updates', 'id', 'maie_update_fk')->restrictOnDelete();
            $table->string('storage_disk', 64)->default('local_private');
            $table->string('storage_path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by_user_id')->constrained('users', 'id', 'maie_uploaded_by_fk')->restrictOnDelete();
            $table->timestamps();
            $table->index(['action_item_id', 'created_at'], 'maie_item_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_action_item_evidence');
        Schema::dropIfExists('meeting_action_item_assignment_history');
        Schema::dropIfExists('meeting_action_item_extension_requests');
        Schema::dropIfExists('meeting_action_item_updates');
        Schema::dropIfExists('meeting_action_items');
        Schema::dropIfExists('meeting_decision_followups');
    }
};
