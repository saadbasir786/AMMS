<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartAuthorityScheduling
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow04Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canStartAuthorityScheduling($actor, $context->authority_office_unit_id), 403, 'UNAUTHORIZED_AUTHORITY_SCHEDULER');
            abort_unless($locked->status === MeetingRequestStatus::HIGH_AUTHORITY_APPROVED->value, 422, 'HIGH_AUTHORITY_APPROVAL_REQUIRED');

            $from = $this->updateStatus($locked, MeetingRequestStatus::AUTHORITY_SCHEDULING);
            $this->record($locked, $actor, 'AUTHORITY_SCHEDULING_STARTED', $from, $locked->status, null, ['authority_office_unit_id' => $context->authority_office_unit_id]);
            $this->notifyUsersForUnit($locked->requesting_unit_id, ['EXECUTIVE_MANAGEMENT', 'MEETING_COORDINATOR'], 'زمان‌بندی مقام عالی آغاز شد', 'دفتر مقام زمان‌بندی رسمی جلسه را آغاز کرد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
