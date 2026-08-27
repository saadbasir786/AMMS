<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartMultiHighAuthorityScheduling
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockMultiHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canScheduler($actor, $context->authority_office_unit_id), 403, 'UNAUTHORIZED_AUTHORITY_SCHEDULER');
            abort_unless($locked->status === MeetingRequestStatus::HIGH_AUTHORITY_APPROVED->value, 422, 'HIGH_AUTHORITY_APPROVAL_REQUIRED');
            $from = $this->transition($locked, MeetingRequestStatus::AUTHORITY_SCHEDULING);
            $this->record($locked, $actor, 'AUTHORITY_SCHEDULING_STARTED', $from, $locked->status, null, ['authority_office_unit_id' => $context->authority_office_unit_id]);
            foreach ($this->formalUnits($locked) as $unit) $this->notifyUnit($unit->id, ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'], 'زمان‌بندی رسمی جلسه با مقام', 'پیشنهاد زمان رسمی دفتر مقام پس از تأیید مقام عالی صادر خواهد شد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
