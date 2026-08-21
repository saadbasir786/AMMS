<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\ParticipantChangeActionSupport;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RefreshParticipantChangeCaseBaseline
{
    use ParticipantChangeActionSupport;

    public function execute(Meeting $meeting, MeetingParticipantChangeCase $case, User $actor): MeetingParticipantChangeCase
    {
        return DB::transaction(function () use ($meeting, $case, $actor): MeetingParticipantChangeCase {
            $meeting = $this->lockMeeting($meeting);
            $case = $this->lockCase($meeting, $case);
            $this->assertManage($actor, $case, $this->governance());
            $this->assertState($case, ['DRAFT', 'RETURNED_FOR_CORRECTION', 'PENDING_CONFIRMATION']);
            abort_unless((int) $meeting->active_participant_change_case_id === (int) $case->id, 422, 'PARTICIPANT_CHANGE_NOT_ACTIVE');
            $this->validator()->assertMeetingEditable($meeting);
            $case->forceFill([
                'baseline_schedule_version_id' => $meeting->current_schedule_version_id,
                'status' => 'DRAFT',
                'submitted_at' => null,
            ])->save();
            $this->record($meeting, $actor, 'PARTICIPANT_CHANGE_BASELINE_REFRESHED', $case->reason, [
                'change_case_id' => $case->id,
                'baseline_schedule_version_id' => $case->baseline_schedule_version_id,
            ]);
            return $case->fresh(['items.oldEmployee.unit', 'items.newEmployee.unit', 'baselineScheduleVersion']);
        });
    }
}
