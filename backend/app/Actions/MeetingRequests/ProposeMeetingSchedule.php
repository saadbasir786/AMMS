<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProposeMeetingSchedule
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-schedule.propose'), 403, 'FORBIDDEN');
            $locked = $this->lockFlow02Request($request);
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::SCHEDULING, 422, 'BILATERAL_APPROVAL_REQUIRED');
            $requester = $this->unitFor($locked, 'REQUESTER');
            $target = $this->unitFor($locked, 'TARGET');
            $unit = OrganizationalUnit::findOrFail($data['proposed_by_unit_id']);
            abort_unless(in_array($unit->id, [$requester->id, $target->id], true), 403, 'FORBIDDEN');
            abort_unless($this->canCoordinateUnit($actor, $unit->id), 403, 'FORBIDDEN');
            abort_unless($unit->status === 'ACTIVE', 422, 'INVALID_TARGET_DIRECTORATE');
            abort_unless(isset($data['meeting_date'], $data['start_time'], $data['end_time'], $data['location']) && filled($data['location']), 422, 'INVALID_SCHEDULE_PROPOSAL');

            MeetingScheduleProposal::query()->where('meeting_request_id', $locked->id)->whereIn('status', ['PROPOSED', 'COUNTER_PROPOSED'])->update(['status' => 'SUPERSEDED']);
            $hasPrevious = MeetingScheduleProposal::where('meeting_request_id', $locked->id)->exists();
            $proposal = $locked->scheduleProposals()->create([
                'proposed_by_unit_id' => $unit->id,
                'proposed_by_user_id' => $actor->id,
                'meeting_date' => $data['meeting_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'location' => $data['location'],
                'comment' => $data['comment'] ?? null,
                'status' => $hasPrevious ? 'COUNTER_PROPOSED' : 'PROPOSED',
            ]);
            $this->record($locked, $actor, $hasPrevious ? 'SCHEDULE_COUNTER_PROPOSED' : 'SCHEDULE_PROPOSED', MeetingRequestStatus::SCHEDULING->value, MeetingRequestStatus::SCHEDULING->value, $data['comment'] ?? null, ['proposal_id' => $proposal->id, 'unit_id' => $unit->id]);
            $opposite = $unit->id === $requester->id ? $target : $requester;
            $this->notifyUnit($opposite->id, ['EXECUTIVE_MANAGEMENT', 'MEETING_COORDINATOR'], 'پیشنهاد جدید زمان‌بندی', 'برای درخواست بین‌ریاستی یک پیشنهاد یا پیشنهاد متقابل زمان‌بندی ثبت شده است.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
