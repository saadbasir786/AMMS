<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateInterDirectorateMeetingRequest
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $this->assertActiveActor($actor);
            $locked = $this->lockFlow02Request($request);
            $requester = $this->unitFor($locked, 'REQUESTER');
            $target = $this->unitFor($locked, 'TARGET');

            abort_unless($actor->can('meeting-request.update') && $this->canManageUnit($actor, $requester->id), 403, 'FORBIDDEN');
            abort_unless(in_array($locked->status, [
                MeetingRequestStatus::DRAFT->value,
                MeetingRequestStatus::RETURNED_TO_REQUESTER->value,
                MeetingRequestStatus::RETURNED_BY_TARGET->value,
            ], true), 422, 'REQUEST_NOT_EDITABLE');

            $material = (array_key_exists('subject', $data) && $data['subject'] !== $locked->subject)
                || (array_key_exists('purpose', $data) && $data['purpose'] !== $locked->purpose);

            $participantIds = $data['participant_ids'] ?? null;
            unset($data['participant_ids']);
            $locked->update($data);

            if ($participantIds !== null) {
                $ids = array_values(array_unique(array_map('intval', $participantIds)));
                $this->assertParticipantEmployees($ids, $requester, $target);
                $locked->participants()->where('participant_type', 'PROPOSED')->delete();
                foreach ($ids as $employeeId) {
                    $locked->participants()->create([
                        'employee_id' => $employeeId,
                        'participant_type' => 'PROPOSED',
                        'created_by' => $actor->id,
                    ]);
                }
            }

            if ($material && $locked->getOriginal('status') === MeetingRequestStatus::RETURNED_BY_TARGET->value) {
                $from = MeetingRequestStatus::RETURNED_BY_TARGET->value;
                $locked->update([
                    'status' => MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value,
                    'requester_approved_by' => null,
                    'requester_approved_at' => null,
                    'target_approved_by' => null,
                    'target_approved_at' => null,
                ]);
                $this->record($locked, $actor, 'MATERIAL_CHANGE_REQUIRES_REAPPROVAL', $from, $locked->status, null, [
                    'requester_unit_id' => $requester->id,
                    'target_unit_id' => $target->id,
                ]);
                $this->notifyUnit($requester->id, ['DIRECTOR'], 'درخواست اصلاح‌شده نیازمند تأیید مجدد است', 'تغییر اساسی پس از برگشت ریاست مقصد انجام شده و تأیید مجدد ریاست درخواست‌کننده لازم است.', $locked);
            } else {
                $this->record($locked, $actor, 'UPDATED', $locked->status, $locked->status, null, ['material' => $material]);
            }

            return $this->responseRequest($locked);
        });
    }
}
