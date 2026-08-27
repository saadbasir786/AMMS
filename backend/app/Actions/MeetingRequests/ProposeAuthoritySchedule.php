<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProposeAuthoritySchedule
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $locked = $this->lockHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canAuthorityScheduler($actor, $context->authority_office_unit_id), 403, 'UNAUTHORIZED_AUTHORITY_SCHEDULER');
            abort_unless($locked->status === MeetingRequestStatus::AUTHORITY_SCHEDULING->value, 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            abort_unless((string) $data['end_time'] > (string) $data['start_time'], 422, 'INVALID_SCHEDULE_TIME');

            $active = $locked->scheduleProposals()->whereIn('status', ['PROPOSED', 'COUNTER_PROPOSED', 'ACCEPTED'])->latest('id')->first();
            if ($active) $active->update(['status' => 'SUPERSEDED']);
            $type = $active ? 'AUTHORITY_REVISED_PROPOSAL' : 'AUTHORITY_OFFICIAL_PROPOSAL';
            $proposal = $locked->scheduleProposals()->create([
                'proposed_by_unit_id' => $context->authority_office_unit_id,
                'proposed_by_user_id' => $actor->id,
                'meeting_date' => $data['meeting_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'location' => $data['location'],
                'comment' => $data['comment'] ?? null,
                'status' => 'PROPOSED',
                'proposal_type' => $type,
            ]);
            $this->record($locked, $actor, 'AUTHORITY_SCHEDULE_PROPOSED', $locked->status, $locked->status, $data['comment'] ?? null, ['proposal_id' => $proposal->id, 'proposal_type' => $type]);
            $this->notifyUsersForUnit($locked->requesting_unit_id, ['EXECUTIVE_MANAGEMENT'], 'پیشنهاد رسمی زمان‌بندی مقام', 'دفتر مقام یک زمان رسمی برای جلسه پیشنهاد کرده است.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
