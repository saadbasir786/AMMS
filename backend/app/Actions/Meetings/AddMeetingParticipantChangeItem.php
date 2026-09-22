<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\ParticipantChangeActionSupport;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\MeetingParticipantChangeItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AddMeetingParticipantChangeItem
{
    use ParticipantChangeActionSupport;

    public function execute(Meeting $meeting, MeetingParticipantChangeCase $case, User $actor, string $operation, ?int $oldEmployeeId, ?int $newEmployeeId, ?string $participantRole = null): MeetingParticipantChangeItem
    {
        return DB::transaction(function () use ($meeting, $case, $actor, $operation, $oldEmployeeId, $newEmployeeId, $participantRole): MeetingParticipantChangeItem {
            $meeting = $this->lockMeeting($meeting);
            $case = $this->lockCase($meeting, $case);
            $this->assertManage($actor, $case, $this->governance());
            $this->assertState($case, ['DRAFT', 'RETURNED_FOR_CORRECTION']);
            $this->validator()->assertMeetingEditable($meeting);
            $this->validator()->assertItem($case, $operation, $oldEmployeeId, $newEmployeeId);

            $operation = strtoupper($operation);
            $duplicate = $case->items->contains(function (MeetingParticipantChangeItem $item) use ($operation, $oldEmployeeId, $newEmployeeId): bool {
                return $item->operation === $operation && (int) ($item->old_employee_id ?? 0) === (int) ($oldEmployeeId ?? 0) && (int) ($item->new_employee_id ?? 0) === (int) ($newEmployeeId ?? 0);
            });
            if ($duplicate) throw new HttpException(422, 'PARTICIPANT_CHANGE_NOT_READY');

            if ($this->governance()->isInternalDirectorate($meeting)) {
                $draftItems = $case->items->map(fn (MeetingParticipantChangeItem $existing): array => [
                    'operation' => $existing->operation,
                    'old_employee_id' => $existing->old_employee_id,
                    'new_employee_id' => $existing->new_employee_id,
                    'participant_role' => $existing->participant_role,
                ])->push([
                    'operation' => $operation,
                    'old_employee_id' => $oldEmployeeId,
                    'new_employee_id' => $newEmployeeId,
                    'participant_role' => $participantRole,
                ])->all();
                $this->validator()->assertInternalDraftConsistent($case, $draftItems);
            }

            $item = $case->items()->create([
                'operation' => $operation,
                'old_employee_id' => $oldEmployeeId,
                'new_employee_id' => $newEmployeeId,
                'participant_role' => $participantRole,
            ]);
            $this->record($meeting, $actor, 'PARTICIPANT_CHANGE_UPDATED', $case->reason, [
                'change_case_id' => $case->id,
                'item_id' => $item->id,
                'operation' => $operation,
                'old_employee_id' => $oldEmployeeId,
                'new_employee_id' => $newEmployeeId,
            ]);
            return $item->load(['oldEmployee.unit', 'newEmployee.unit']);
        });
    }
}
