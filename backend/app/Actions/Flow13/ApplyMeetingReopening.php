<?php

declare(strict_types=1);

namespace App\Actions\Flow13;

use App\Actions\Flow13\Concerns\Flow13ActionSupport;
use App\Actions\Flow13\Concerns\Flow13MeetingStateSupport;
use App\Models\Meeting;
use App\Models\MeetingExceptionCase;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;
use Illuminate\Support\Facades\DB;

class ApplyMeetingReopening
{
    use Flow13ActionSupport, Flow13MeetingStateSupport;

    public function __construct(private MeetingExceptionGovernanceResolver $governance) {}

    public function execute(MeetingExceptionCase $case, User $actor): MeetingExceptionCase
    {
        $case->loadMissing(['meeting', 'currentVersion']);
        abort_unless($case->case_type === MeetingExceptionCase::REOPENING && $this->governance->mayApply($case, $actor), 403, 'UNAUTHORIZED_MEETING_EXCEPTION');
        return DB::transaction(function () use ($case, $actor): MeetingExceptionCase {
            $locked = MeetingExceptionCase::query()->with(['meeting.request', 'currentVersion'])->lockForUpdate()->findOrFail($case->id);
            $meeting = Meeting::query()->with(['request'])->lockForUpdate()->findOrFail($locked->meeting_id);
            abort_unless($locked->status === MeetingExceptionCase::READY_TO_APPLY, 409, 'MEETING_EXCEPTION_REVIEW_INCOMPLETE');
            abort_unless($meeting->status === 'CANCELLED', 409, $meeting->status === 'COMPLETED' ? 'COMPLETED_MEETING_REOPEN_NOT_ALLOWED' : ($meeting->status === 'HELD' ? 'HELD_MEETING_REOPEN_NOT_ALLOWED' : 'MEETING_REOPEN_NOT_ALLOWED'));
            $this->assertBaseline($locked, $meeting);
            $meeting->forceFill(['status' => 'POSTPONED'])->save();
            $locked->forceFill(['status' => MeetingExceptionCase::APPLIED, 'active_marker' => null, 'applied_by_user_id' => $actor->id, 'applied_at' => now(), 'updated_at' => now()])->save();
            $this->audit($meeting, $actor, 'MEETING_REOPENED', $locked->currentVersion->reason, $this->exceptionMetadata($locked));
            $this->notify($meeting->coordinator_id, 'MEETING_REOPENED', 'جلسه بازگشایی شد', 'جلسه بازگشایی شد و اکنون برای تعیین زمان جدید به حالت تعویق‌افتاده است.', ['meeting_id' => $meeting->id, 'meeting_number' => $meeting->meeting_number]);
            return $locked->fresh(['meeting', 'currentVersion']);
        });
    }
}
