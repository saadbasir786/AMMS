<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_reschedule_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->restrictOnDelete();
            $table->unsignedBigInteger('baseline_schedule_version_id')->nullable();
            $table->foreignId('initiated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('initiated_by_unit_id')->nullable()->constrained('organizational_units')->nullOnDelete();
            $table->text('reason');
            $table->string('governance_mode', 50);
            $table->string('status', 40)->default('OPEN')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['meeting_id', 'status']);
        });

        Schema::create('meeting_schedule_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->date('meeting_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location');
            $table->unsignedBigInteger('source_reschedule_case_id')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['meeting_id', 'version_number']);
            $table->index(['meeting_id', 'created_at']);
        });

        Schema::create('meeting_reschedule_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reschedule_case_id')->constrained('meeting_reschedule_cases')->restrictOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->restrictOnDelete();
            $table->foreignId('proposed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('proposed_by_unit_id')->nullable()->constrained('organizational_units')->nullOnDelete();
            $table->string('proposal_type', 40);
            $table->date('meeting_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location');
            $table->text('comment')->nullable();
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->foreignId('supersedes_proposal_id')->nullable()->constrained('meeting_reschedule_proposals')->nullOnDelete();
            $table->timestamps();
            $table->index(['reschedule_case_id', 'status']);
        });

        Schema::create('meeting_reschedule_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proposal_id')->constrained('meeting_reschedule_proposals')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('acknowledged_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at')->useCurrent();
            $table->unique(['proposal_id', 'organizational_unit_id']);
        });

        Schema::create('meeting_reschedule_alternative_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reschedule_case_id')->constrained('meeting_reschedule_cases')->cascadeOnDelete();
            $table->foreignId('proposal_id')->constrained('meeting_reschedule_proposals')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->date('suggested_date')->nullable();
            $table->time('suggested_start_time')->nullable();
            $table->time('suggested_end_time')->nullable();
            $table->string('status', 20)->default('OPEN')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['reschedule_case_id', 'status']);
        });

        Schema::create('meeting_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 80);
            $table->text('comment')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['meeting_id', 'created_at']);
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_schedule_version_id')->nullable()->after('status');
            $table->unsignedBigInteger('active_reschedule_case_id')->nullable()->after('current_schedule_version_id');
            $table->index('current_schedule_version_id');
            $table->index('active_reschedule_case_id');
        });

        Schema::table('meeting_reschedule_cases', function (Blueprint $table): void {
            $table->foreign('baseline_schedule_version_id', 'reschedule_cases_baseline_version_fk')
                ->references('id')->on('meeting_schedule_versions')->nullOnDelete();
        });

        Schema::table('meeting_schedule_versions', function (Blueprint $table): void {
            $table->foreign('source_reschedule_case_id', 'schedule_versions_source_case_fk')
                ->references('id')->on('meeting_reschedule_cases')->nullOnDelete();
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->foreign('current_schedule_version_id', 'meetings_current_schedule_version_fk')
                ->references('id')->on('meeting_schedule_versions')->nullOnDelete();
            $table->foreign('active_reschedule_case_id', 'meetings_active_reschedule_case_fk')
                ->references('id')->on('meeting_reschedule_cases')->nullOnDelete();
        });

        $this->backfillInitialScheduleVersions();
    }

    private function backfillInitialScheduleVersions(): void
    {
        $meetings = DB::table('meetings')->orderBy('id')->get();
        $invalidIds = $meetings->filter(fn ($meeting): bool => !filled($meeting->meeting_date)
            || !filled($meeting->start_time)
            || !filled($meeting->end_time)
            || !filled($meeting->location))->pluck('id')->values()->all();

        if ($invalidIds !== []) {
            throw new \RuntimeException('FLOW07_BACKFILL_INVALID_MEETING_IDS: '.implode(',', $invalidIds));
        }

        foreach ($meetings as $meeting) {
            $versionId = DB::table('meeting_schedule_versions')
                ->where('meeting_id', $meeting->id)
                ->where('version_number', 1)
                ->value('id');

            if (!$versionId) {
                $versionId = DB::table('meeting_schedule_versions')->insertGetId([
                    'meeting_id' => $meeting->id,
                    'version_number' => 1,
                    'meeting_date' => $meeting->meeting_date,
                    'start_time' => $meeting->start_time,
                    'end_time' => $meeting->end_time,
                    'location' => $meeting->location,
                    'source_reschedule_case_id' => null,
                    'created_by_user_id' => $meeting->coordinator_id,
                    'created_at' => now(),
                ]);
            }

            if (!$meeting->current_schedule_version_id) {
                DB::table('meetings')->where('id', $meeting->id)->update([
                    'current_schedule_version_id' => $versionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropForeign('meetings_current_schedule_version_fk');
            $table->dropForeign('meetings_active_reschedule_case_fk');
            $table->dropIndex(['current_schedule_version_id']);
            $table->dropIndex(['active_reschedule_case_id']);
            $table->dropColumn(['current_schedule_version_id', 'active_reschedule_case_id']);
        });

        Schema::table('meeting_schedule_versions', function (Blueprint $table): void {
            $table->dropForeign('schedule_versions_source_case_fk');
        });
        Schema::table('meeting_reschedule_cases', function (Blueprint $table): void {
            $table->dropForeign('reschedule_cases_baseline_version_fk');
        });

        Schema::dropIfExists('meeting_actions');
        Schema::dropIfExists('meeting_reschedule_alternative_requests');
        Schema::dropIfExists('meeting_reschedule_acknowledgements');
        Schema::dropIfExists('meeting_reschedule_proposals');
        Schema::dropIfExists('meeting_schedule_versions');
        Schema::dropIfExists('meeting_reschedule_cases');
    }
};
