<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingAuthorityScheduleAcknowledgement;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmAuthoritySchedule
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, MeetingScheduleProposal $proposal): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $proposal): MeetingRequest {
            $locked = $this->lockFlow04Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canAuthorityScheduler($actor, $context->authority_office_unit_id) && $actor->can('authority-schedule.confirm'), 403, 'UNAUTHORIZED_AUTHORITY_SCHEDULER');
            abort_unless($locked->status === MeetingRequestStatus::AUTHORITY_SCHEDULING->value, 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            abort_unless($proposal->meeting_request_id === $locked->id, 404, 'PROPOSAL_NOT_FOUND');
            abort_unless(in_array($proposal->proposal_type, ['AUTHORITY_OFFICIAL_PROPOSAL', 'AUTHORITY_REVISED_PROPOSAL'], true) && $proposal->status === 'PROPOSED', 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            abort_unless(MeetingAuthorityScheduleAcknowledgement::where('meeting_schedule_proposal_id', $proposal->id)->where('requesting_unit_id', $locked->requesting_unit_id)->exists(), 422, 'AUTHORITY_SCHEDULE_NOT_ACKNOWLEDGED');
            abort_unless((string) $proposal->end_time > (string) $proposal->start_time, 422, 'INVALID_SCHEDULE_TIME');

            $proposal->update(['status' => 'ACCEPTED']);
            $from = $this->updateStatus($locked, MeetingRequestStatus::SCHEDULE_CONFIRMED);
            $locked->update([
                'schedule_confirmed_by' => $actor->id,
                'schedule_confirmed_at' => now(),
                'preferred_date' => $proposal->meeting_date,
                'preferred_start_time' => $proposal->start_time,
                'proposed_location' => $proposal->location,
            ]);
            $this->record($locked, $actor, 'AUTHORITY_SCHEDULE_CONFIRMED', $from, $locked->status, null, ['proposal_id' => $proposal->id, 'proposal_type' => $proposal->proposal_type]);
            $this->notifyUser($locked->created_by, 'زمان‌بندی رسمی مقام تأیید شد', 'دفتر مقام زمان رسمی جلسه را نهایی کرد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
