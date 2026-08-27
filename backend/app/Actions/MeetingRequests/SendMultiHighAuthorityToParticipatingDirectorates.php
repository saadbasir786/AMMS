<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SendMultiHighAuthorityToParticipatingDirectorates
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockMultiHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            abort_unless($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->id === $locked->lead_coordinator_user_id, 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::REQUESTER_APPROVED->value, 422, 'REQUESTER_APPROVAL_REQUIRED');
            $from = $this->transition($locked, MeetingRequestStatus::SUBMITTED_TO_PARTICIPATING_DIRECTORATES);
            $this->record($locked, $actor, 'REQUEST_SENT_TO_PARTICIPATING_DIRECTORATES', $from, $locked->status, null, ['unit_ids' => $this->participatingUnits($locked)->pluck('id')->all()]);
            foreach ($this->participatingUnits($locked) as $unit) $this->notifyUnit($unit->id, ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'], 'درخواست چندریاستی جدید', 'درخواست برای بررسی ریاست اشتراک‌کننده ارسال شد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
