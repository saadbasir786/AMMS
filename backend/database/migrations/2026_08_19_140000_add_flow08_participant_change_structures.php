<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_participant_change_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings', 'id', 'mpcc_meeting_fk')->restrictOnDelete();
            $table->string('owner_type', 20);
            $table->foreignId('owner_unit_id')->nullable()->constrained('organizational_units', 'id', 'mpcc_owner_unit_fk')->nullOnDelete();
            $table->unsignedBigInteger('baseline_participant_version_id');
            $table->unsignedBigInteger('baseline_schedule_version_id')->nullable();
            $table->foreignId('initiated_by_user_id')->constrained('users', 'id', 'mpcc_initiated_user_fk')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 32)->default('DRAFT')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['meeting_id', 'status'], 'mpcc_meeting_status_idx');
        });

        Schema::create('meeting_participant_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings', 'id', 'mpv_meeting_fk')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->unsignedBigInteger('source_change_case_id')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users', 'id', 'mpv_created_user_fk')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['meeting_id', 'version_number'], 'participant_versions_meeting_version_unique');
            $table->index(['meeting_id', 'created_at'], 'mpv_meeting_created_idx');
        });

        Schema::create('meeting_participant_version_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('participant_version_id')->constrained('meeting_participant_versions', 'id', 'mpvm_version_fk')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees', 'id', 'mpvm_employee_fk')->restrictOnDelete();
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units', 'id', 'mpvm_unit_fk')->nullOnDelete();
            $table->string('participant_role')->nullable();
            $table->string('owner_type', 20);
            $table->foreignId('owner_unit_id')->nullable()->constrained('organizational_units', 'id', 'mpvm_owner_unit_fk')->nullOnDelete();
            $table->boolean('is_mandatory')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['participant_version_id', 'employee_id'], 'participant_version_members_employee_unique');
            $table->index(['participant_version_id', 'owner_type', 'owner_unit_id'], 'mpvm_version_owner_idx');
        });

        Schema::create('meeting_participant_change_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('change_case_id')->constrained('meeting_participant_change_cases', 'id', 'mpci_case_fk')->cascadeOnDelete();
            $table->string('operation', 16);
            $table->foreignId('old_employee_id')->nullable()->constrained('employees', 'id', 'mpci_old_employee_fk')->restrictOnDelete();
            $table->foreignId('new_employee_id')->nullable()->constrained('employees', 'id', 'mpci_new_employee_fk')->restrictOnDelete();
            $table->string('participant_role')->nullable();
            $table->timestamps();
            $table->index(['change_case_id', 'operation'], 'mpci_case_operation_idx');
        });

        Schema::table('meeting_participant_change_cases', function (Blueprint $table): void {
            $table->foreign('baseline_participant_version_id', 'participant_cases_baseline_version_fk')
                ->references('id')->on('meeting_participant_versions')->restrictOnDelete();
            $table->foreign('baseline_schedule_version_id', 'participant_cases_baseline_schedule_fk')
                ->references('id')->on('meeting_schedule_versions')->restrictOnDelete();
        });

        Schema::table('meeting_participant_versions', function (Blueprint $table): void {
            $table->foreign('source_change_case_id', 'participant_versions_source_case_fk')
                ->references('id')->on('meeting_participant_change_cases')->nullOnDelete();
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_participant_version_id')->nullable()->after('current_schedule_version_id');
            $table->unsignedBigInteger('active_participant_change_case_id')->nullable()->after('current_participant_version_id');
            $table->index('current_participant_version_id');
            $table->index('active_participant_change_case_id');
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->foreign('current_participant_version_id', 'meetings_current_participant_version_fk')
                ->references('id')->on('meeting_participant_versions')->nullOnDelete();
            $table->foreign('active_participant_change_case_id', 'meetings_active_participant_case_fk')
                ->references('id')->on('meeting_participant_change_cases')->nullOnDelete();
        });

        $this->backfillInitialParticipantVersions();
    }

    private function backfillInitialParticipantVersions(): void
    {
        $meetings = DB::table('meetings')->orderBy('id')->get();
        $invalidIds = [];

        foreach ($meetings as $meeting) {
            $participants = DB::table('meeting_participants')
                ->where('meeting_id', $meeting->id)
                ->orderBy('id')
                ->get();

            if ($participants->isEmpty()) {
                $invalidIds[] = $meeting->id;
                continue;
            }

            foreach ($participants as $participant) {
                if (!$participant->employee_id || !$participant->organizational_unit_id) {
                    $invalidIds[] = $meeting->id;
                    break;
                }
            }
        }

        if ($invalidIds !== []) {
            throw new \RuntimeException('FLOW08_BACKFILL_INVALID_MEETING_IDS: '.implode(',', array_values(array_unique($invalidIds))));
        }

        foreach ($meetings as $meeting) {
            $versionId = DB::table('meeting_participant_versions')
                ->where('meeting_id', $meeting->id)
                ->where('version_number', 1)
                ->value('id');

            if (!$versionId) {
                $versionId = DB::table('meeting_participant_versions')->insertGetId([
                    'meeting_id' => $meeting->id,
                    'version_number' => 1,
                    'source_change_case_id' => null,
                    'created_by_user_id' => $meeting->coordinator_id,
                    'created_at' => now(),
                ]);
            }

            $formalUnitIds = DB::table('meeting_units')
                ->where('meeting_id', $meeting->id)
                ->pluck('organizational_unit_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $authorityEmployeeId = (int) (DB::table('meeting_authority_contexts')
                ->where('meeting_id', $meeting->id)
                ->value('authority_employee_id') ?? 0);

            $membersExist = DB::table('meeting_participant_version_members')
                ->where('participant_version_id', $versionId)
                ->exists();

            if (!$membersExist) {
                $rows = DB::table('meeting_participants')
                    ->where('meeting_id', $meeting->id)
                    ->orderBy('id')
                    ->get()
                    ->map(function ($participant) use ($versionId, $formalUnitIds, $authorityEmployeeId): array {
                        $isUnitOwned = in_array((int) $participant->organizational_unit_id, $formalUnitIds, true);
                        $isHighAuthority = $authorityEmployeeId > 0 && (int) $participant->employee_id === $authorityEmployeeId;
                        return [
                            'participant_version_id' => $versionId,
                            'employee_id' => $participant->employee_id,
                            'organizational_unit_id' => $participant->organizational_unit_id,
                            'participant_role' => $participant->role,
                            'owner_type' => $isUnitOwned ? 'UNIT' : 'AUTHORITY',
                            'owner_unit_id' => $isUnitOwned ? $participant->organizational_unit_id : null,
                            'is_mandatory' => $isHighAuthority,
                            'created_at' => now(),
                        ];
                    })
                    ->all();
                DB::table('meeting_participant_version_members')->insert($rows);
            }

            if (!$meeting->current_participant_version_id) {
                DB::table('meetings')->where('id', $meeting->id)->update([
                    'current_participant_version_id' => $versionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropForeign('meetings_current_participant_version_fk');
            $table->dropForeign('meetings_active_participant_case_fk');
            $table->dropIndex(['current_participant_version_id']);
            $table->dropIndex(['active_participant_change_case_id']);
            $table->dropColumn(['current_participant_version_id', 'active_participant_change_case_id']);
        });

        Schema::table('meeting_participant_versions', function (Blueprint $table): void {
            $table->dropForeign('participant_versions_source_case_fk');
        });
        Schema::table('meeting_participant_change_cases', function (Blueprint $table): void {
            $table->dropForeign('participant_cases_baseline_version_fk');
            $table->dropForeign('participant_cases_baseline_schedule_fk');
        });

        Schema::dropIfExists('meeting_participant_change_items');
        Schema::dropIfExists('meeting_participant_version_members');
        Schema::dropIfExists('meeting_participant_versions');
        Schema::dropIfExists('meeting_participant_change_cases');
    }
};
