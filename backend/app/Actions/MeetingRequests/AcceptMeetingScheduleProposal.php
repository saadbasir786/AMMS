<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptMeetingScheduleProposal
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, MeetingScheduleProposal $proposal): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $proposal): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-schedule.accept'), 403, 'FORBIDDEN');
            $locked = $this->lockFlow02Request($request);
            abort_unless($proposal->meeting_request_id === $locked->id, 404, 'INVALID_SCHEDULE_PROPOSAL');
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::SCHEDULING, 422, 'SCHEDULE_NOT_CONFIRMED');
            $requester = $this->unitFor($locked, 'REQUESTER');
            $target = $this->unitFor($locked, 'TARGET');
            abort_unless(in_array($proposal->status, ['PROPOSED', 'COUNTER_PROPOSED'], true), 422, 'SCHEDULE_NOT_ACCEPTED');
            $opposite = $proposal->proposed_by_unit_id === $requester->id ? $target : $requester;
            abort_unless($this->canCoordinateUnit($actor, $opposite->id), 403, 'FORBIDDEN');
            MeetingScheduleProposal::query()->where('meeting_request_id', $locked->id)->whereIn('status', ['PROPOSED', 'COUNTER_PROPOSED'])->whereKeyNot($proposal->id)->update(['status' => 'SUPERSEDED']);
            $proposal->update(['status' => 'ACCEPTED']);
            $this->record($locked, $actor, 'SCHEDULE_ACCEPTED', MeetingRequestStatus::SCHEDULING->value, MeetingRequestStatus::SCHEDULING->value, $proposal->comment, ['proposal_id' => $proposal->id]);
            $this->notifyUnit($proposal->proposed_by_unit_id, ['EXECUTIVE_MANAGEMENT', 'MEETING_COORDINATOR'], 'پیشنهاد زمان‌بندی پذیرفته شد', 'پیشنهاد زمان‌بندی شما توسط ریاست مقابل پذیرفته شد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
