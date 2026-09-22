<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\ParticipantChangeActionSupport;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReturnMeetingParticipantChange
{
    use ParticipantChangeActionSupport;

    public function execute(Meeting $meeting, MeetingParticipantChangeCase $case, User $actor, string $reason): MeetingParticipantChangeCase
    {
        return DB::transaction(function () use ($meeting, $case, $actor, $reason): MeetingParticipantChangeCase {
            $meeting = $this->lockMeeting($meeting);
            $case = $this->lockCase($meeting, $case);
            $this->assertConfirm($actor, $case, $this->governance());
            if ($this->governance()->isInternalDirectorate($meeting) && !$actor->can('meeting-participant-change.return')) {
                throw new HttpException(403, 'UNAUTHORIZED_PARTICIPANT_CONFIRMATION');
            }
            $this->assertState($case, ['PENDING_CONFIRMATION']);
            $reason = $this->validator()->assertReason($reason);
            $case->forceFill(['status' => 'RETURNED_FOR_CORRECTION'])->save();
            $this->record($meeting, $actor, 'PARTICIPANT_CHANGE_RETURNED', $reason, [
                'change_case_id' => $case->id,
                'reviewer_user_id' => $actor->id,
                'reviewer_name' => $actor->name,
                'reviewed_at' => now()->toIso8601String(),
                'reason' => $reason,
            ]);
            if ($this->governance()->isInternalDirectorate($meeting)) {
                $this->notifyScopedRole((int) $case->owner_unit_id, 'EXECUTIVE_MANAGEMENT', 'PARTICIPANT_CHANGE_RETURNED', 'پرونده تغییر اعضا برای اصلاح برگشت داده شد', 'مدیر جلسه پرونده تغییر اعضا را برای اصلاح برگشت داده است. دلیل در فضای کاری پرونده ثبت شده است.', $meeting, (int) $case->initiated_by_user_id);
                $this->notifyUser((int) $case->initiated_by_user_id, 'PARTICIPANT_CHANGE_RETURNED', 'پرونده تغییر اعضا برای اصلاح برگشت داده شد', 'مدیر جلسه پرونده تغییر اعضا را برای اصلاح برگشت داده است. دلیل در فضای کاری پرونده ثبت شده است.', $meeting);
            }
            return $case->fresh(['items.oldEmployee.unit', 'items.newEmployee.unit']);
        });
    }
}
