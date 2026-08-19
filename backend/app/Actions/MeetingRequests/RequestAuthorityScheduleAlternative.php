<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestAuthorityScheduleAlternative
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $locked = $this->lockFlow04Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $locked->status === 'AUTHORITY_SCHEDULING', 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            abort_unless($this->canManageRequester($actor, $locked->requesting_unit_id) || $actor->hasScopedRole('MEETING_COORDINATOR', $locked->requesting_unit_id), 403, 'FORBIDDEN');
            abort_unless(filled(trim($data['reason'] ?? '')), 422, 'REASON_REQUIRED');
            abort_unless((string) $data['end_time'] > (string) $data['start_time'], 422, 'INVALID_SCHEDULE_TIME');

            $active = $locked->scheduleProposals()->whereIn('status', ['PROPOSED', 'COUNTER_PROPOSED', 'ACCEPTED'])->latest('id')->first();
            abort_unless($active, 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            $active->update(['status' => 'SUPERSEDED']);
            $proposal = $locked->scheduleProposals()->create([
                'proposed_by_unit_id' => $locked->requesting_unit_id,
                'proposed_by_user_id' => $actor->id,
                'meeting_date' => $data['meeting_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'location' => $data['location'] ?? $locked->proposed_location ?? 'دفتر مقام',
                'comment' => $data['reason'],
                'status' => 'COUNTER_PROPOSED',
                'proposal_type' => 'REQUESTER_ALTERNATIVE_REQUEST',
            ]);
            $this->record($locked, $actor, 'REQUESTER_REQUESTED_ALTERNATIVE', $locked->status, $locked->status, $data['reason'], ['proposal_id' => $proposal->id]);
            $this->notifyUsersForUnit($context->authority_office_unit_id, ['AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY'], 'درخواست زمان بدیل', 'ریاست درخواست‌کننده برای زمان رسمی درخواست بدیل فرستاده است.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
