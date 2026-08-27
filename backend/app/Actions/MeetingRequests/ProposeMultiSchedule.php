<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProposeMultiSchedule
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $locked = $this->lockMultiDirectorateRequest($request);
            $this->assertActiveActor($actor);
            abort_unless($locked->status === MeetingRequestStatus::SCHEDULING->value, 422, 'SCHEDULING_REQUIRED');
            $unitId = (int) $data['proposed_by_unit_id'];
            $unit = $this->assertParticipatingMember($locked, $unitId);
            abort_unless($this->canCoordinateUnit($actor, $unit->id), 403, 'FORBIDDEN');

            $active = $locked->scheduleProposals->whereIn('status', ['PROPOSED', 'COUNTER_PROPOSED', 'ACCEPTED'])->sortByDesc('id')->first();
            $status = $active ? 'COUNTER_PROPOSED' : 'PROPOSED';
            if ($active) {
                $active->update(['status' => 'SUPERSEDED']);
            }

            $proposal = $locked->scheduleProposals()->create([
                'proposed_by_unit_id' => $unit->id,
                'proposed_by_user_id' => $actor->id,
                'meeting_date' => $data['meeting_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'location' => $data['location'],
                'comment' => $data['comment'] ?? null,
                'status' => $status,
            ]);
            $proposal->acceptances()->create([
                'organizational_unit_id' => $unit->id,
                'accepted_by' => $actor->id,
                'accepted_at' => now(),
            ]);

            $this->record($locked, $actor, $status === 'COUNTER_PROPOSED' ? 'SCHEDULE_COUNTER_PROPOSED' : 'SCHEDULE_PROPOSED', $locked->status, $locked->status, $data['comment'] ?? null, [
                'unit_id' => $unit->id,
                'proposal_id' => $proposal->id,
            ]);
            foreach ($this->participatingUnits($locked)->where('id', '!=', $unit->id) as $other) {
                $this->notifyUnit($other->id, ['EXECUTIVE_MANAGEMENT'], 'پیشنهاد زمان‌بندی جدید', $unit->name.' پیشنهاد جدیدی برای جلسه ثبت کرد.', $locked);
            }

            return $this->responseRequest($locked);
        });
    }
}
