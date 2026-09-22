<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_participant_change_cases', function (Blueprint $table): void {
            $table->foreignId('submitted_by_user_id')->nullable()->after('submitted_at')->constrained('users', 'id', 'mpcc_submitted_user_fk')->restrictOnDelete();
            $table->unsignedInteger('review_cycle')->default(0)->after('submitted_by_user_id');
            $table->foreignId('approved_by_user_id')->nullable()->after('confirmed_at')->constrained('users', 'id', 'mpcc_approved_user_fk')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->unsignedBigInteger('approved_participant_version_id')->nullable()->after('approved_at');
            $table->unsignedBigInteger('approved_schedule_version_id')->nullable()->after('approved_participant_version_id');
            $table->string('approved_draft_hash', 64)->nullable()->after('approved_schedule_version_id');
            $table->foreign('approved_participant_version_id', 'mpcc_approved_participant_version_fk')->references('id')->on('meeting_participant_versions')->restrictOnDelete();
            $table->foreign('approved_schedule_version_id', 'mpcc_approved_schedule_version_fk')->references('id')->on('meeting_schedule_versions')->restrictOnDelete();
        });

        DB::table('meeting_participant_change_cases')
            ->whereIn('status', ['PENDING_CONFIRMATION', 'RETURNED_FOR_CORRECTION'])
            ->orderBy('id')
            ->get(['id', 'meeting_id', 'initiated_by_user_id', 'submitted_by_user_id', 'review_cycle'])
            ->each(function ($case): void {
                $submitterId = null;
                $reviewCycle = 0;
                $submissions = DB::table('meeting_actions')
                    ->where('meeting_id', $case->meeting_id)
                    ->where('action', 'PARTICIPANT_CHANGE_SUBMITTED')
                    ->orderBy('id')
                    ->get(['metadata']);

                foreach ($submissions as $submission) {
                    $metadata = is_array($submission->metadata) ? $submission->metadata : json_decode((string) $submission->metadata, true);
                    if ((int) ($metadata['change_case_id'] ?? 0) !== (int) $case->id) continue;
                    $submitterId = (int) ($metadata['submitted_by_user_id'] ?? 0) ?: (int) $case->initiated_by_user_id;
                    $reviewCycle++;
                }

                if ($submitterId) {
                    DB::table('meeting_participant_change_cases')->where('id', $case->id)->update([
                        'submitted_by_user_id' => $submitterId,
                        'review_cycle' => $reviewCycle,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('meeting_participant_change_cases', function (Blueprint $table): void {
            $table->dropForeign('mpcc_submitted_user_fk');
            $table->dropForeign('mpcc_approved_user_fk');
            $table->dropForeign('mpcc_approved_participant_version_fk');
            $table->dropForeign('mpcc_approved_schedule_version_fk');
            $table->dropColumn([
                'submitted_by_user_id',
                'review_cycle',
                'approved_by_user_id',
                'approved_at',
                'approved_participant_version_id',
                'approved_schedule_version_id',
                'approved_draft_hash',
            ]);
        });
    }
};
