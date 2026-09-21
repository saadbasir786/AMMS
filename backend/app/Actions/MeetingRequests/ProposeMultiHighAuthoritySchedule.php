<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProposeMultiHighAuthoritySchedule
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $locked = $this->lockMultiHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canScheduler($actor, $context->authority_office_unit_id), 403, 'UNAUTHORIZED_AUTHORITY_SCHEDULER');
            abort_unless($locked->status === 'AUTHORITY_SCHEDULING', 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            abort_unless((string) $data['end_time'] > (string) $data['start_time'], 422, 'INVALID_SCHEDULE_TIME');
            $schedule = app(\App\Support\ScheduleVersionService::class)->normalize($data, $locked);
            $locked->scheduleProposals()->whereIn('status', ['PROPOSED', 'COUNTER_PROPOSED', 'ACCEPTED', 'ALTERNATIVE_REQUESTED'])->update(['status' => 'SUPERSEDED']);
            $proposal = $locked->scheduleProposals()->create(['proposed_by_unit_id' => $context->authority_office_unit_id, 'proposed_by_user_id' => $actor->id, ...$schedule, 'comment' => $data['comment'] ?? null, 'status' => 'PROPOSED', 'proposal_type' => 'AUTHORITY_OFFICIAL']);
            $this->record($locked, $actor, 'AUTHORITY_SCHEDULE_PROPOSED', $locked->status, $locked->status, $data['comment'] ?? null, ['proposal_id' => $proposal->id, 'proposal_type' => 'AUTHORITY_OFFICIAL']);
            foreach ($this->formalUnits($locked) as $unit) $this->notifyUnit($unit->id, ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'], 'پیشنهاد رسمی زمان‌بندی', 'دفتر مقام پیشنهاد رسمی جدید صادر کرد؛ پاسخ ریاست خود را ثبت کنید.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
