<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_authority_schedule_unit_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_schedule_proposal_id')->constrained('meeting_schedule_proposals')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('acknowledged_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->unique(['meeting_request_id', 'meeting_schedule_proposal_id', 'organizational_unit_id'], 'multi_high_schedule_unit_ack_unique');
            $table->index(['meeting_request_id', 'organizational_unit_id', 'acknowledged_at'], 'multi_high_schedule_unit_ack_lookup');
        });

        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->index(['flow_type', 'status'], 'meeting_request_flow_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_requests', function (Blueprint $table): void {
            $table->dropIndex('meeting_request_flow_status_idx');
        });

        Schema::dropIfExists('meeting_authority_schedule_unit_acknowledgements');
    }
};
