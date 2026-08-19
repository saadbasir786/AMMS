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
            $this->assertState($case, ['PENDING_CONFIRMATION']);
            $reason = $this->validator()->assertReason($reason);
            $case->forceFill(['status' => 'RETURNED_FOR_CORRECTION'])->save();
            $this->record($meeting, $actor, 'PARTICIPANT_CHANGE_RETURNED', $reason, ['change_case_id' => $case->id]);
            return $case->fresh(['items.oldEmployee.unit', 'items.newEmployee.unit']);
        });
    }
}
