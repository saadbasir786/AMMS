<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->foreignId('lead_coordinator_unit_id')->nullable()->after('coordinator_id')->constrained('organizational_units')->nullOnDelete();
            $table->foreignId('lead_coordinator_user_id')->nullable()->after('lead_coordinator_unit_id')->constrained('users')->nullOnDelete();
            $table->foreignId('requester_approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('requester_approved_at')->nullable()->after('requester_approved_by');
            $table->foreignId('target_approved_by')->nullable()->after('requester_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('target_approved_at')->nullable()->after('target_approved_by');
            $table->foreignId('requester_participants_confirmed_by')->nullable()->after('target_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('requester_participants_confirmed_at')->nullable()->after('requester_participants_confirmed_by');
            $table->foreignId('target_participants_confirmed_by')->nullable()->after('requester_participants_confirmed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('target_participants_confirmed_at')->nullable()->after('target_participants_confirmed_by');
            $table->foreignId('schedule_confirmed_by')->nullable()->after('target_participants_confirmed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('schedule_confirmed_at')->nullable()->after('schedule_confirmed_by');
            $table->index(['flow_type', 'status']);
            $table->index(['lead_coordinator_unit_id', 'status']);
        });

        Schema::create('meeting_request_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->string('role', 30);
            $table->timestamps();
            $table->unique(['meeting_request_id', 'role'], 'request_unit_one_role');
            $table->unique(['meeting_request_id', 'organizational_unit_id'], 'request_unit_one_unit');
            $table->index(['organizational_unit_id', 'role']);
        });

        Schema::create('meeting_schedule_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposed_by_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('proposed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->date('meeting_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location');
            $table->text('comment')->nullable();
            $table->string('status', 30)->default('PROPOSED');
            $table->timestamps();
            $table->index(['meeting_request_id', 'status', 'created_at'], 'schedule_proposal_request_status');
            $table->index(['proposed_by_unit_id', 'created_at']);
        });

        Schema::create('meeting_request_participant_nominations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained('meeting_requests', 'id', 'mrpn_request_fk')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees', 'id', 'mrpn_employee_fk')->restrictOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units', 'id', 'mrpn_unit_fk')->restrictOnDelete();
            $table->foreignId('nominated_by_unit_id')->constrained('organizational_units', 'id', 'mrpn_nom_unit_fk')->restrictOnDelete();
            $table->foreignId('nominated_by_user_id')->constrained('users', 'id', 'mrpn_nom_user_fk')->restrictOnDelete();
            $table->string('participant_stage', 30)->default('PROPOSED');
            $table->string('nomination_status', 30)->default('NOMINATED');
            $table->timestamps();
            $table->unique(['meeting_request_id', 'employee_id', 'participant_stage'], 'request_nomination_unique');
            $table->index(['meeting_request_id', 'nominated_by_unit_id', 'nomination_status'], 'request_nomination_side_status');
        });

        Schema::create('meeting_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->string('role', 30);
            $table->timestamps();
            $table->unique(['meeting_id', 'role'], 'meeting_unit_one_role');
            $table->unique(['meeting_id', 'organizational_unit_id'], 'meeting_unit_one_unit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_units');
        Schema::dropIfExists('meeting_request_participant_nominations');
        Schema::dropIfExists('meeting_schedule_proposals');
        Schema::dropIfExists('meeting_request_units');
        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->dropIndex(['flow_type', 'status']);
            $table->dropIndex(['lead_coordinator_unit_id', 'status']);
            $table->dropForeign(['lead_coordinator_unit_id']);
            $table->dropForeign(['lead_coordinator_user_id']);
            $table->dropForeign(['requester_approved_by']);
            $table->dropForeign(['target_approved_by']);
            $table->dropForeign(['requester_participants_confirmed_by']);
            $table->dropForeign(['target_participants_confirmed_by']);
            $table->dropForeign(['schedule_confirmed_by']);
            $table->dropColumn([
                'lead_coordinator_unit_id', 'lead_coordinator_user_id', 'requester_approved_by', 'requester_approved_at',
                'target_approved_by', 'target_approved_at', 'requester_participants_confirmed_by', 'requester_participants_confirmed_at',
                'target_participants_confirmed_by', 'target_participants_confirmed_at', 'schedule_confirmed_by', 'schedule_confirmed_at',
            ]);
        });
    }
};
