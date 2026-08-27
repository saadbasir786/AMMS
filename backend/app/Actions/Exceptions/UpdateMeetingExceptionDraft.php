<?php

declare(strict_types=1);

namespace App\Actions\Exceptions;

use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionCaseVersion;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateMeetingExceptionDraft
{
    public function __construct(private MeetingExceptionGovernanceResolver $governance) {}

    public function execute(MeetingExceptionCase $case, User $actor, string $reason, ?string $administrativeNote = null, bool $replacementExpected = false, ?string $proposedResumeDate = null): MeetingExceptionCase
    {
        $case->loadMissing(['meeting', 'currentVersion']);
        abort_unless($this->governance->mayUpdateDraft($case, $actor), 403, 'UNAUTHORIZED_MEETING_EXCEPTION');
        $reason = trim($reason);
        abort_unless($reason !== '', 422, 'MEETING_EXCEPTION_REASON_REQUIRED');
        if ($case->case_type === MeetingExceptionCase::POSTPONEMENT) abort_unless(!$replacementExpected, 422, 'POSTPONEMENT_REPLACEMENT_SCHEDULE_REQUIRES_FLOW07');
        if ($case->case_type === MeetingExceptionCase::RESUMPTION) abort_unless($replacementExpected, 422, 'MEETING_RESUMPTION_SCHEDULE_REQUIRED');
        abort_unless(in_array($case->status, [MeetingExceptionCase::DRAFT, MeetingExceptionCase::RETURNED_FOR_CORRECTION], true), 409, 'MEETING_EXCEPTION_NOT_ALLOWED');

        return DB::transaction(function () use ($case, $actor, $reason, $administrativeNote, $replacementExpected, $proposedResumeDate): MeetingExceptionCase {
            $locked = MeetingExceptionCase::query()->with('currentVersion')->lockForUpdate()->findOrFail($case->id);
            abort_unless(in_array($locked->status, [MeetingExceptionCase::DRAFT, MeetingExceptionCase::RETURNED_FOR_CORRECTION], true), 409, 'MEETING_EXCEPTION_NOT_ALLOWED');
            $current = $locked->currentVersion;
            if ($locked->status === MeetingExceptionCase::DRAFT && $current && !$current->isSubmitted()) {
                $current->update(['reason' => $reason, 'administrative_note' => $administrativeNote, 'replacement_expected' => $replacementExpected, 'proposed_resume_date' => $proposedResumeDate]);
                return $locked->fresh(['currentVersion', 'versions']);
            }
            $version = $locked->versions()->create([
                'uuid' => (string) Str::uuid(), 'version_number' => ((int) $locked->versions()->max('version_number')) + 1,
                'reason' => $reason, 'administrative_note' => $administrativeNote, 'replacement_expected' => $replacementExpected,
                'proposed_resume_date' => $proposedResumeDate, 'baseline_meeting_status' => $locked->meeting->status,
                'baseline_schedule_version_id' => $locked->meeting->current_schedule_version_id, 'baseline_participant_version_id' => $locked->meeting->current_participant_version_id,
                'prepared_by_user_id' => $actor->id, 'created_at' => now(),
            ]);
            $locked->forceFill(['current_version_id' => $version->id, 'status' => MeetingExceptionCase::DRAFT, 'baseline_meeting_status' => $locked->meeting->status, 'baseline_schedule_version_id' => $locked->meeting->current_schedule_version_id, 'baseline_participant_version_id' => $locked->meeting->current_participant_version_id, 'updated_at' => now()])->save();
            return $locked->fresh(['currentVersion', 'versions']);
        });
    }
}
