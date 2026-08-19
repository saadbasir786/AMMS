<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\ParticipantChangeActionSupport;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateMeetingParticipantChangeCase
{
    use ParticipantChangeActionSupport;

    public function execute(Meeting $meeting, User $actor, string $reason): MeetingParticipantChangeCase
    {
        return DB::transaction(function () use ($meeting, $actor, $reason): MeetingParticipantChangeCase {
            $meeting = $this->lockMeeting($meeting);
            $this->assertActive($actor);
            $this->validator()->assertMeetingEditable($meeting);
            $owner = $this->governance()->actorOwner($meeting, $actor);
            if (!$owner || !$this->governance()->mayPrepare($meeting, $actor, $owner['owner_type'], $owner['owner_unit_id'])) throw new HttpException(403, 'UNAUTHORIZED_PARTICIPANT_CHANGE');
            if ($meeting->active_participant_change_case_id) throw new HttpException(422, 'PARTICIPANT_CHANGE_ALREADY_IN_PROGRESS');
            $reason = $this->validator()->assertReason($reason);
            if (!$meeting->current_participant_version_id) throw new HttpException(422, 'PARTICIPANT_CHANGE_NOT_READY');

            $case = MeetingParticipantChangeCase::create([
                'meeting_id' => $meeting->id,
                'owner_type' => $owner['owner_type'],
                'owner_unit_id' => $owner['owner_unit_id'],
                'baseline_participant_version_id' => $meeting->current_participant_version_id,
                'baseline_schedule_version_id' => $meeting->current_schedule_version_id,
                'initiated_by_user_id' => $actor->id,
                'reason' => $reason,
                'status' => 'DRAFT',
            ]);
            $meeting->forceFill(['active_participant_change_case_id' => $case->id])->save();
            $this->record($meeting, $actor, 'PARTICIPANT_CHANGE_CASE_CREATED', $reason, [
                'change_case_id' => $case->id,
                'owner_type' => $case->owner_type,
                'owner_unit_id' => $case->owner_unit_id,
                'baseline_participant_version_id' => $case->baseline_participant_version_id,
                'baseline_schedule_version_id' => $case->baseline_schedule_version_id,
            ]);
            return $case->load(['items', 'ownerUnit', 'baselineParticipantVersion.members.employee.unit', 'baselineScheduleVersion']);
        });
    }
}
