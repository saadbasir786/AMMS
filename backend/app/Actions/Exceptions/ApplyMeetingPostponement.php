<?php

declare(strict_types=1);

namespace App\Actions\Exceptions;

use App\Actions\Exceptions\Concerns\Flow13ActionSupport;
use App\Actions\Exceptions\Concerns\Flow13MeetingStateSupport;
use App\Models\Meeting;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingAttendanceInvalidation;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;
use Illuminate\Support\Facades\DB;

class ApplyMeetingPostponement
{
    use Flow13ActionSupport, Flow13MeetingStateSupport;

    public function __construct(private MeetingExceptionGovernanceResolver $governance) {}

    public function execute(MeetingExceptionCase $case, User $actor): MeetingExceptionCase
    {
        $case->loadMissing(['meeting', 'currentVersion']);
        abort_unless($case->case_type === MeetingExceptionCase::POSTPONEMENT && $this->governance->mayApply($case, $actor), 403, 'UNAUTHORIZED_MEETING_EXCEPTION');
        return DB::transaction(function () use ($case, $actor): MeetingExceptionCase {
            $locked = MeetingExceptionCase::query()->with(['meeting.request', 'currentVersion'])->lockForUpdate()->findOrFail($case->id);
            $meeting = Meeting::query()->with(['request', 'terminalAssignments', 'attendanceRecords'])->lockForUpdate()->findOrFail($locked->meeting_id);
            $this->assertCaseReadyForApplication($locked);
            abort_unless($meeting->status === 'SCHEDULED', 409, 'MEETING_POSTPONEMENT_NOT_ALLOWED');
            abort_unless($locked->currentVersion && !$locked->currentVersion->replacement_expected, 422, 'POSTPONEMENT_REPLACEMENT_SCHEDULE_REQUIRES_FLOW07');
            $this->assertBaseline($locked, $meeting);
            $meeting->forceFill(['status' => 'POSTPONED'])->save();
            $this->revokeTerminalAccess($meeting, $actor, 'MEETING_POSTPONED');
            $this->invalidateAttendance($meeting, $locked, $actor, MeetingAttendanceInvalidation::POSTPONED);
            $locked->forceFill(['status' => MeetingExceptionCase::APPLIED, 'active_marker' => null, 'applied_by_user_id' => $actor->id, 'applied_at' => now(), 'updated_at' => now()])->save();
            $metadata = $this->exceptionMetadata($locked);
            $metadata['reason'] = $locked->currentVersion->reason;
            $this->audit($meeting, $actor, 'MEETING_POSTPONED', $locked->currentVersion->reason, $metadata);
            $this->notify($meeting->coordinator_id, 'MEETING_POSTPONED', 'جلسه به تعویق افتاد', 'جلسه به تعویق افتاد؛ زمان جدید هنوز تأیید نشده است.', ['meeting_id' => $meeting->id, 'meeting_number' => $meeting->meeting_number]);
            return $locked->fresh(['meeting', 'currentVersion']);
        });
    }
}
