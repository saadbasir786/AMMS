<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FLOW-02 keeps one REQUESTER and one TARGET in application invariants.
        // FLOW-03 needs one REQUESTER and multiple TARGET rows, so role uniqueness
        // is intentionally removed while the request+unit uniqueness remains.
        Schema::table('meeting_request_units', function (Blueprint $table): void {
            $table->dropUnique('request_unit_one_role');
            $table->index(['meeting_request_id', 'role'], 'request_unit_request_role');
        });

        Schema::table('meeting_units', function (Blueprint $table): void {
            // FLOW-03 has one REQUESTING row and multiple PARTICIPATING rows.
            $table->dropUnique('meeting_unit_one_role');
            $table->index(['meeting_id', 'role'], 'meeting_unit_meeting_role');
        });

        Schema::create('meeting_request_unit_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained('meeting_requests', 'id', 'mru_review_request_fk')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units', 'id', 'mru_review_unit_fk')->restrictOnDelete();
            $table->string('review_status', 40)->default('PENDING_REVIEW');
            $table->foreignId('reviewed_by')->nullable()->constrained('users', 'id', 'mru_review_user_fk')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['meeting_request_id', 'organizational_unit_id'], 'request_unit_review_unique');
            $table->index(['organizational_unit_id', 'review_status'], 'request_unit_review_unit_status');
        });

        Schema::create('meeting_schedule_proposal_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_schedule_proposal_id')->constrained('meeting_schedule_proposals', 'id', 'mspa_proposal_fk')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units', 'id', 'mspa_unit_fk')->restrictOnDelete();
            $table->foreignId('accepted_by')->constrained('users', 'id', 'mspa_user_fk')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->unique(['meeting_schedule_proposal_id', 'organizational_unit_id'], 'schedule_acceptance_unit_unique');
            $table->index(['organizational_unit_id', 'accepted_at'], 'schedule_acceptance_unit_time');
        });

        Schema::create('meeting_request_unit_participant_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_request_id')->constrained('meeting_requests', 'id', 'mru_confirm_request_fk')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units', 'id', 'mru_confirm_unit_fk')->restrictOnDelete();
            $table->foreignId('confirmed_by')->constrained('users', 'id', 'mru_confirm_user_fk')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->timestamps();
            $table->unique(['meeting_request_id', 'organizational_unit_id'], 'participant_confirmation_unit_unique');
            $table->index(['organizational_unit_id', 'confirmed_at'], 'participant_confirmation_unit_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_request_unit_participant_confirmations');
        Schema::dropIfExists('meeting_schedule_proposal_acceptances');
        Schema::dropIfExists('meeting_request_unit_reviews');

        Schema::table('meeting_units', function (Blueprint $table): void {
            $table->dropIndex('meeting_unit_meeting_role');
            $table->unique(['meeting_id', 'role'], 'meeting_unit_one_role');
        });

        Schema::table('meeting_request_units', function (Blueprint $table): void {
            $table->dropIndex('request_unit_request_role');
            $table->unique(['meeting_request_id', 'role'], 'request_unit_one_role');
        });
    }
};
