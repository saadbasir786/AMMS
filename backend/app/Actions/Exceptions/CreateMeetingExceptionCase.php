<?php

declare(strict_types=1);

namespace App\Actions\Exceptions;

use App\Actions\Exceptions\Concerns\ExceptionWorkflowSupport;
use App\Models\Meeting;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionCaseVersion;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateMeetingExceptionCase
{
    use ExceptionWorkflowSupport;

    public function __construct(private MeetingExceptionGovernanceResolver $governance) {}

    public function execute(Meeting $meeting, User $actor, string $caseType, string $reason, bool $replacementExpected = false, ?string $proposedResumeDate = null, ?string $administrativeNote = null): MeetingExceptionCase
    {
        abort_unless($this->governance->mayPrepare($meeting, $actor), 403, 'UNAUTHORIZED_MEETING_EXCEPTION');
        $reason = trim($reason);
        abort_unless($reason !== '', 422, 'MEETING_EXCEPTION_REASON_REQUIRED');
        abort_unless(in_array($caseType, [MeetingExceptionCase::POSTPONEMENT, MeetingExceptionCase::CANCELLATION, MeetingExceptionCase::REOPENING, MeetingExceptionCase::RESUMPTION], true), 422, 'MEETING_EXCEPTION_NOT_ALLOWED');
        abort_unless($this->allowedFromStatus($meeting, $caseType), 409, $this->statusError($meeting, $caseType));
        if ($caseType === MeetingExceptionCase::POSTPONEMENT) abort_unless(!$replacementExpected, 422, 'POSTPONEMENT_REPLACEMENT_SCHEDULE_REQUIRED');
        if ($caseType === MeetingExceptionCase::RESUMPTION) abort_unless($replacementExpected, 422, 'MEETING_RESUMPTION_SCHEDULE_REQUIRED');

        try {
            return DB::transaction(function () use ($meeting, $actor, $caseType, $reason, $replacementExpected, $proposedResumeDate, $administrativeNote): MeetingExceptionCase {
                $lockedMeeting = Meeting::query()->with(['request', 'authorityContext', 'units'])->lockForUpdate()->findOrFail($meeting->id);
                abort_unless($this->allowedFromStatus($lockedMeeting, $caseType), 409, $this->statusError($lockedMeeting, $caseType));
                abort_unless(!MeetingExceptionCase::query()->where('meeting_id', $lockedMeeting->id)->where('active_marker', 1)->exists(), 409, 'MEETING_EXCEPTION_ACTIVE_CASE_EXISTS');
                $governance = $this->governance->resolve($lockedMeeting);
                $case = MeetingExceptionCase::create([
                    'uuid' => (string) Str::uuid(), 'meeting_id' => $lockedMeeting->id, 'case_type' => $caseType,
                    'status' => MeetingExceptionCase::DRAFT, 'governance_mode' => $governance['mode'],
                    'baseline_meeting_status' => $lockedMeeting->status, 'baseline_schedule_version_id' => $lockedMeeting->current_schedule_version_id,
                    'baseline_participant_version_id' => $lockedMeeting->current_participant_version_id, 'created_by_user_id' => $actor->id,
                    'active_marker' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $version = $case->versions()->create([
                    'uuid' => (string) Str::uuid(), 'version_number' => 1, 'reason' => $reason, 'administrative_note' => $administrativeNote,
                    'replacement_expected' => $replacementExpected, 'proposed_resume_date' => $proposedResumeDate,
                    'baseline_meeting_status' => $lockedMeeting->status, 'baseline_schedule_version_id' => $lockedMeeting->current_schedule_version_id,
                    'baseline_participant_version_id' => $lockedMeeting->current_participant_version_id, 'prepared_by_user_id' => $actor->id,
                    'created_at' => now(),
                ]);
                $case->forceFill(['current_version_id' => $version->id])->save();
                $this->audit($lockedMeeting, $actor, 'MEETING_EXCEPTION_CASE_CREATED', $reason, ['exception_case_id' => $case->id, 'case_version_id' => $version->id, 'case_type' => $caseType]);
                return $case->fresh(['meeting', 'currentVersion', 'versions']);
            });
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'mec_one_active_uq') || str_contains(strtolower($e->getMessage()), 'unique')) abort(409, 'MEETING_EXCEPTION_ACTIVE_CASE_EXISTS');
            throw $e;
        }
    }

    private function allowedFromStatus(Meeting $meeting, string $caseType): bool
    {
        return match ($caseType) {
            MeetingExceptionCase::POSTPONEMENT => $meeting->status === 'SCHEDULED',
            MeetingExceptionCase::CANCELLATION => in_array($meeting->status, ['SCHEDULED', 'POSTPONED'], true),
            MeetingExceptionCase::REOPENING => $meeting->status === 'CANCELLED',
            MeetingExceptionCase::RESUMPTION => $meeting->status === 'POSTPONED',
            default => false,
        };
    }

    private function statusError(Meeting $meeting, string $caseType): string
    {
        return match ($caseType) {
            MeetingExceptionCase::POSTPONEMENT => $meeting->status === 'POSTPONED' ? 'MEETING_ALREADY_POSTPONED' : 'MEETING_POSTPONEMENT_NOT_ALLOWED',
            MeetingExceptionCase::CANCELLATION => $meeting->status === 'CANCELLED' ? 'MEETING_ALREADY_CANCELLED' : ($meeting->status === 'IN_PROGRESS' ? 'MEETING_EXECUTION_ALREADY_STARTED' : 'MEETING_CANCELLATION_NOT_ALLOWED'),
            MeetingExceptionCase::REOPENING => in_array($meeting->status, ['COMPLETED', 'HELD'], true) ? ($meeting->status === 'COMPLETED' ? 'COMPLETED_MEETING_REOPEN_NOT_ALLOWED' : 'HELD_MEETING_REOPEN_NOT_ALLOWED') : 'MEETING_REOPEN_NOT_ALLOWED',
            MeetingExceptionCase::RESUMPTION => 'MEETING_RESUMPTION_NOT_ALLOWED',
            default => 'MEETING_EXCEPTION_NOT_ALLOWED',
        };
    }
}
