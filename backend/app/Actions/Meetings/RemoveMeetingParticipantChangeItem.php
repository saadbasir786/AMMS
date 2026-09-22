<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\ParticipantChangeActionSupport;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\MeetingParticipantChangeItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RemoveMeetingParticipantChangeItem
{
    use ParticipantChangeActionSupport;

    public function execute(Meeting $meeting, MeetingParticipantChangeCase $case, User $actor, int $employeeId): MeetingParticipantChangeItem
    {
        return DB::transaction(function () use ($meeting, $case, $actor, $employeeId): MeetingParticipantChangeItem {
            $meeting = $this->lockMeeting($meeting);
            $case = $this->lockCase($meeting, $case);
            $this->assertManage($actor, $case, $this->governance());
            $this->assertState($case, ['DRAFT', 'RETURNED_FOR_CORRECTION']);
            $this->validator()->assertMeetingEditable($meeting);
            $this->validator()->assertItem($case, 'REMOVE', $employeeId, null);

            if ($this->governance()->isInternalDirectorate($meeting)) {
                $draftItems = $case->items->map(fn (MeetingParticipantChangeItem $existing): array => [
                    'operation' => $existing->operation,
                    'old_employee_id' => $existing->old_employee_id,
                    'new_employee_id' => $existing->new_employee_id,
                    'participant_role' => $existing->participant_role,
                ])->push([
                    'operation' => 'REMOVE',
                    'old_employee_id' => $employeeId,
                    'new_employee_id' => null,
                    'participant_role' => null,
                ])->all();
                $this->validator()->assertInternalDraftConsistent($case, $draftItems);
            }

            $item = $case->items()->create([
                'operation' => 'REMOVE',
                'old_employee_id' => $employeeId,
                'new_employee_id' => null,
            ]);
            $this->record($meeting, $actor, 'PARTICIPANT_CHANGE_UPDATED', $case->reason, [
                'change_case_id' => $case->id,
                'item_id' => $item->id,
                'operation' => 'REMOVE',
                'old_employee_id' => $employeeId,
            ]);
            return $item->load('oldEmployee.unit');
        });
    }
}
