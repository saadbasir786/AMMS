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
            $item = $case->items->firstWhere('id', (int) $item->id);
            if (!$item) throw new HttpException(404, 'PARTICIPANT_CHANGE_ITEM_NOT_FOUND');
            if ($this->governance()->isInternalDirectorate($meeting)) {
                $draftItems = $case->items->reject(fn (MeetingParticipantChangeItem $existing): bool => (int) $existing->id === (int) $item->id)
                    ->map(fn (MeetingParticipantChangeItem $existing): array => [
                        'operation' => $existing->operation,
                        'old_employee_id' => $existing->old_employee_id,
                        'new_employee_id' => $existing->new_employee_id,
                        'participant_role' => $existing->participant_role,
                    ])->values()->all();
                $this->validator()->assertInternalDraftConsistent($case, $draftItems);
            }
            $this->record($meeting, $actor, 'PARTICIPANT_CHANGE_ITEM_DELETED', $case->reason, [
                'change_case_id' => $case->id,
                'deleted_item_id' => $item->id,
                'operation' => $item->operation,
                'old_employee_id' => $item->old_employee_id,
                'new_employee_id' => $item->new_employee_id,
            ]);
            $item->delete();
        });
    }
}
