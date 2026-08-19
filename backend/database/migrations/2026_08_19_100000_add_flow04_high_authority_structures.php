<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->text('meeting_justification')->nullable()->after('description');
            $table->text('requested_outcome')->nullable()->after('meeting_justification');
            $table->index(['flow_type', 'status', 'requesting_unit_id'], 'meeting_request_flow_status_unit');
        });

        Schema::table('meeting_schedule_proposals', function (Blueprint $table): void {
            $table->string('proposal_type', 50)->nullable()->after('status');
            $table->index(['meeting_request_id', 'proposal_type', 'status'], 'authority_schedule_proposal_type');
        });

        Schema::create('organization_authorities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('authority_type', 40);
            $table->foreignId('authority_position_id')->constrained('positions')->restrictOnDelete();
            $table->foreignId('authority_office_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->string('status', 20)->default('ACTIVE');
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'is_primary', 'status'], 'organization_authority_primary_status_unique');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('meeting_request_authority_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_authority_id')->constrained('organization_authorities')->restrictOnDelete();
            $table->string('authority_type', 40);
            $table->foreignId('authority_office_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('authority_position_id')->constrained('positions')->restrictOnDelete();
            $table->foreignId('authority_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->string('office_review_status', 40)->default('PENDING');
            $table->foreignId('office_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('office_reviewed_at')->nullable();
            $table->text('office_review_comment')->nullable();
            $table->foreignId('forwarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('forwarded_at')->nullable();
            $table->string('authority_decision_status', 40)->default('PENDING');
            $table->foreignId('authority_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authority_decided_at')->nullable();
            $table->text('authority_decision_comment')->nullable();
            $table->timestamps();
            $table->index(['authority_employee_id', 'authority_decision_status'], 'request_authority_employee_decision');
            $table->index(['authority_office_unit_id', 'office_review_status'], 'request_authority_office_review');
        });

        Schema::create('meeting_authority_schedule_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_schedule_proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requesting_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('acknowledged_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->unique(['meeting_request_id', 'meeting_schedule_proposal_id', 'requesting_unit_id'], 'authority_schedule_ack_unique');
            $table->index(['meeting_request_id', 'acknowledged_at']);
        });

        Schema::create('meeting_authority_participant_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained()->cascadeOnDelete();
            $table->string('side', 20);
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->timestamps();
            $table->unique(['meeting_request_id', 'side'], 'authority_participant_confirmation_side_unique');
            $table->index(['meeting_request_id', 'side']);
        });

        Schema::create('meeting_authority_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_authority_id')->constrained('organization_authorities')->restrictOnDelete();
            $table->string('authority_type', 40);
            $table->foreignId('authority_position_id')->constrained('positions')->restrictOnDelete();
            $table->foreignId('authority_employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('authority_office_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->timestamps();
            $table->index(['authority_employee_id', 'meeting_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_authority_contexts');
        Schema::dropIfExists('meeting_authority_participant_confirmations');
        Schema::dropIfExists('meeting_authority_schedule_acknowledgements');
        Schema::dropIfExists('meeting_request_authority_contexts');
        Schema::dropIfExists('organization_authorities');

        Schema::table('meeting_schedule_proposals', function (Blueprint $table): void {
            $table->dropIndex('authority_schedule_proposal_type');
            $table->dropColumn('proposal_type');
        });
        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->dropIndex('meeting_request_flow_status_unit');
            $table->dropColumn(['meeting_justification', 'requested_outcome']);
        });
    }
};
