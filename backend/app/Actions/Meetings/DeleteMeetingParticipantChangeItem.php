<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\ParticipantChangeActionSupport;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\MeetingParticipantChangeItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteMeetingParticipantChangeItem
{
    use ParticipantChangeActionSupport;

    public function execute(Meeting $meeting, MeetingParticipantChangeCase $case, MeetingParticipantChangeItem $item, User $actor): void
    {
        DB::transaction(function () use ($meeting, $case, $item, $actor): void {
            $meeting = $this->lockMeeting($meeting);
            $case = $this->lockCase($meeting, $case);
            $this->assertManage($actor, $case, $this->governance());
            $this->assertState($case, ['DRAFT', 'RETURNED_FOR_CORRECTION']);
            if ((int) $item->change_case_id !== (int) $case->id) throw new HttpException(404, 'PARTICIPANT_CHANGE_ITEM_NOT_FOUND');
            $item->delete();
            $this->record($meeting, $actor, 'PARTICIPANT_CHANGE_UPDATED', $case->reason, [
                'change_case_id' => $case->id,
                'deleted_item_id' => $item->id,
            ]);
        });
    }
}
