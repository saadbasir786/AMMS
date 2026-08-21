<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->foreignId('requesting_unit_id')->nullable()->change();
            $table->text('directive')->nullable()->after('description');
            $table->text('proposed_agenda')->nullable()->after('requested_outcome');
            $table->index(['flow_type', 'status', 'created_by'], 'flow06_request_queue_idx');
        });

        Schema::create('meeting_request_directive_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained('meeting_requests', 'id', 'fdv_request_fk')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('version_status', 40)->default('DRAFT');
            $table->string('subject');
            $table->string('purpose');
            $table->text('description')->nullable();
            $table->text('directive');
            $table->text('meeting_justification')->nullable();
            $table->text('requested_outcome');
            $table->string('priority')->default('NORMAL');
            $table->date('preferred_date')->nullable();
            $table->time('preferred_start_time')->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->string('preferred_location')->nullable();
            $table->text('proposed_agenda')->nullable();
            $table->json('participating_unit_ids');
            $table->foreignId('prepared_by')->constrained('users', 'id', 'fdv_prepared_user_fk')->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users', 'id', 'fdv_issued_user_fk')->nullOnDelete();
            $table->foreignId('authority_employee_id')->nullable()->constrained('employees', 'id', 'fdv_authority_employee_fk')->nullOnDelete();
            $table->foreignId('authority_position_id')->nullable()->constrained('positions', 'id', 'fdv_authority_position_fk')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->unique(['meeting_request_id', 'version'], 'flow06_directive_version_unique');
            $table->index(['meeting_request_id', 'is_current'], 'flow06_directive_current_idx');
            $table->index(['version_status', 'issued_at'], 'flow06_directive_status_idx');
        });

        Schema::create('meeting_request_unit_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained('meeting_requests', 'id', 'fur_request_fk')->cascadeOnDelete();
            $table->foreignId('meeting_request_directive_version_id')->constrained('meeting_request_directive_versions', 'id', 'fur_directive_fk')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units', 'id', 'fur_unit_fk')->restrictOnDelete();
            $table->string('response_status', 40)->default('PENDING_ACKNOWLEDGEMENT');
            $table->foreignId('coordination_started_by')->nullable()->constrained('users', 'id', 'fur_coord_user_fk')->nullOnDelete();
            $table->timestamp('coordination_started_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users', 'id', 'fur_ack_user_fk')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['meeting_request_id', 'meeting_request_directive_version_id', 'organizational_unit_id'], 'flow06_unit_response_unique');
            $table->index(['meeting_request_id', 'response_status'], 'flow06_unit_response_status_idx');
        });

        Schema::create('meeting_request_directive_clarifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained('meeting_requests', 'id', 'fdc_request_fk')->cascadeOnDelete();
            $table->foreignId('meeting_request_directive_version_id')->constrained('meeting_request_directive_versions', 'id', 'fdc_directive_fk')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units', 'id', 'fdc_unit_fk')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users', 'id', 'fdc_requested_user_fk')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->text('reason');
            $table->foreignId('responded_by')->nullable()->constrained('users', 'id', 'fdc_responded_user_fk')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();
            $table->index(['meeting_request_id', 'meeting_request_directive_version_id', 'responded_at'], 'flow06_clarification_lookup');
        });

        Schema::table('meeting_request_authority_contexts', function (Blueprint $table): void {
            $table->string('origin_type', 40)->default('DIRECTORATE_INITIATED')->after('authority_type');
            $table->foreignId('current_directive_version_id')->nullable()->after('authority_employee_id')->constrained('meeting_request_directive_versions', 'id', 'fac_current_directive_fk')->nullOnDelete();
            $table->index(['origin_type', 'authority_employee_id'], 'flow06_authority_origin_idx');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_request_authority_contexts', function (Blueprint $table): void {
            $table->dropForeign('fac_current_directive_fk');
            $table->dropIndex('flow06_authority_origin_idx');
            $table->dropColumn(['origin_type', 'current_directive_version_id']);
        });

        Schema::dropIfExists('meeting_request_directive_clarifications');
        Schema::dropIfExists('meeting_request_unit_responses');
        Schema::dropIfExists('meeting_request_directive_versions');

        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->dropIndex('flow06_request_queue_idx');
            $table->dropColumn(['directive', 'proposed_agenda']);
            $table->foreignId('requesting_unit_id')->nullable(false)->change();
        });
    }
};
