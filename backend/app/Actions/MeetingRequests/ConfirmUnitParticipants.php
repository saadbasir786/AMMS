<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmUnitParticipants
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId): MeetingRequest {
            $locked = $this->lockMultiDirectorateRequest($request);
            $this->assertActiveActor($actor);
            abort_unless($locked->status === 'SCHEDULING', 422, 'SCHEDULING_REQUIRED');
            $unit = $this->assertParticipatingMember($locked, $unitId);
            abort_unless($this->canManageUnit($actor, $unit->id), 403, 'FORBIDDEN');
            abort_if($locked->participantConfirmations->contains('organizational_unit_id', $unit->id), 422, 'UNIT_PARTICIPANTS_ALREADY_CONFIRMED');

            $nominations = $locked->nominations->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $unit->id);
            abort_unless($nominations->isNotEmpty(), 422, 'ALL_UNIT_PARTICIPANTS_REQUIRED');
            $nominations->each(fn ($nomination) => $nomination->update(['nomination_status' => 'CONFIRMED']));
            $locked->participantConfirmations()->create([
                'organizational_unit_id' => $unit->id,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);

            $this->record($locked, $actor, 'UNIT_PARTICIPANTS_CONFIRMED', $locked->status, $locked->status, null, ['unit_id' => $unit->id]);
            $this->notifyUser($locked->lead_coordinator_user_id, 'فهرست اشتراک‌کنندگان یک ریاست تأیید شد', $unit->name.' فهرست نهایی اشتراک‌کنندگان خود را تأیید کرد.', $locked);

            return $this->responseRequest($locked);
        });
    }
}
