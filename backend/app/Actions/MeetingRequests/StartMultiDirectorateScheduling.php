<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartMultiDirectorateScheduling
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow03Request($request);
            $this->assertActiveActor($actor);
            $lead = $locked->lead_coordinator_unit_id ?: $this->requesterUnit($locked)->id;
            abort_unless($this->canCoordinateUnit($actor, $lead), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::MULTILATERAL_APPROVED->value, 422, 'MULTILATERAL_APPROVAL_REQUIRED');
            abort_unless($this->allTargetsApproved($locked), 422, 'ALL_TARGET_APPROVALS_REQUIRED');

            $from = $this->updateStatus($locked, MeetingRequestStatus::SCHEDULING);
            $this->record($locked, $actor, 'SCHEDULING_STARTED', $from, $locked->status, null, ['unit_id' => $lead]);
            foreach ($this->participatingUnits($locked) as $unit) {
                $this->notifyUnit($unit->id, ['EXECUTIVE_MANAGEMENT'], 'زمان‌بندی جلسهٔ چندریاستی آغاز شد', 'ریاست‌ها می‌توانند پیشنهاد زمان‌بندی ثبت کنند.', $locked);
            }

            return $this->responseRequest($locked);
        });
    }
}
