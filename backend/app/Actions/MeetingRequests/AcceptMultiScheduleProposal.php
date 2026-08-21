<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptMultiScheduleProposal
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, MeetingScheduleProposal $proposal, ?int $unitId = null): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $proposal, $unitId): MeetingRequest {
            $locked = $this->lockFlow03Request($request);
            $this->assertActiveActor($actor);
            abort_unless($locked->status === MeetingRequestStatus::SCHEDULING->value, 422, 'SCHEDULING_REQUIRED');

            $lockedProposal = MeetingScheduleProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $lockedProposal->load('acceptances');
            abort_unless((int) $lockedProposal->meeting_request_id === (int) $locked->id, 404, 'PROPOSAL_NOT_FOUND');
            abort_unless(in_array($lockedProposal->status, ['PROPOSED', 'COUNTER_PROPOSED', 'ACCEPTED'], true), 422, 'PROPOSAL_NOT_ACTIVE');

            $participatingIds = $locked->units->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->values();
            $candidateIds = $unitId ? collect([(int) $unitId]) : collect();
            if (!$candidateIds->count()) {
                $candidateIds = $participatingIds->filter(fn (int $id) => $this->canCoordinateUnit($actor, $id));
            }
            abort_unless($candidateIds->count() === 1, 403, 'UNIT_SCOPE_REQUIRED');
            $acceptedUnitId = (int) $candidateIds->first();
            $this->assertParticipatingMember($locked, $acceptedUnitId);
            abort_unless($this->canCoordinateUnit($actor, $acceptedUnitId), 403, 'FORBIDDEN');
            abort_if($lockedProposal->acceptances->contains('organizational_unit_id', $acceptedUnitId), 422, 'UNIT_SCHEDULE_ALREADY_ACCEPTED');

            $lockedProposal->acceptances()->create([
                'organizational_unit_id' => $acceptedUnitId,
                'accepted_by' => $actor->id,
                'accepted_at' => now(),
            ]);
            $this->record($locked, $actor, 'SCHEDULE_UNIT_ACCEPTED', $locked->status, $locked->status, null, [
                'unit_id' => $acceptedUnitId,
                'proposal_id' => $lockedProposal->id,
            ]);

            $lockedProposal->load('acceptances');
            if ($lockedProposal->acceptances->pluck('organizational_unit_id')->unique()->count() >= $participatingIds->count()) {
                $lockedProposal->update(['status' => 'ACCEPTED']);
                $this->record($locked, $actor, 'SCHEDULE_ACCEPTED_BY_ALL', $locked->status, $locked->status, null, [
                    'proposal_id' => $lockedProposal->id,
                    'unit_ids' => $participatingIds->all(),
                ]);
                foreach ($this->participatingUnits($locked) as $unit) {
                    $this->notifyUnit($unit->id, ['EXECUTIVE_MANAGEMENT'], 'زمان‌بندی توسط همهٔ ریاست‌ها پذیرفته شد', 'پیشنهاد فعال توسط همهٔ ریاست‌های مشارکت‌کننده پذیرفته شد.', $locked);
                }
            }

            return $this->responseRequest($locked);
        });
    }
}
