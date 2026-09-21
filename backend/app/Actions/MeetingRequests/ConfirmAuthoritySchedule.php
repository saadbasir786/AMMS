<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingAuthorityScheduleAcknowledgement;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\User;
use App\Support\CentralScheduleConflictService;
use Illuminate\Support\Facades\DB;

class ConfirmAuthoritySchedule
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, MeetingScheduleProposal $proposal): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $proposal): MeetingRequest {
            $locked = $this->lockHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canAuthorityScheduler($actor, $context->authority_office_unit_id) && $actor->can('authority-schedule.confirm'), 403, 'UNAUTHORIZED_AUTHORITY_SCHEDULER');
            abort_unless($locked->status === MeetingRequestStatus::AUTHORITY_SCHEDULING->value, 422, 'AUTHORITY_SCHEDULING_REQUIRED');

            $lockedProposal = MeetingScheduleProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless((int) $lockedProposal->meeting_request_id === (int) $locked->id, 404, 'PROPOSAL_NOT_FOUND');
            abort_unless(in_array($lockedProposal->proposal_type, ['AUTHORITY_OFFICIAL_PROPOSAL', 'AUTHORITY_REVISED_PROPOSAL'], true) && $lockedProposal->status === 'PROPOSED', 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            abort_unless(MeetingAuthorityScheduleAcknowledgement::where('meeting_schedule_proposal_id', $lockedProposal->id)->where('requesting_unit_id', $locked->requesting_unit_id)->exists(), 422, 'AUTHORITY_SCHEDULE_NOT_ACKNOWLEDGED');
            abort_unless((string) $lockedProposal->end_time > (string) $lockedProposal->start_time, 422, 'INVALID_SCHEDULE_TIME');

            $lockedProposal->update(['status' => 'ACCEPTED']);
            $from = $this->updateStatus($locked, MeetingRequestStatus::SCHEDULE_CONFIRMED);
            $locked->update([
                'schedule_confirmed_by' => $actor->id,
                'schedule_confirmed_at' => now(),
                'scheduled_date' => $lockedProposal->meeting_date,
                'scheduled_start_time' => $lockedProposal->start_time,
                'scheduled_duration_minutes' => $this->durationMinutes($lockedProposal->start_time, $lockedProposal->end_time),
                'scheduled_location' => $lockedProposal->location,
                'scheduled_venue_type' => $lockedProposal->venue_type ?: 'EXTERNAL_LOCATION',
                'scheduled_venue_id' => $lockedProposal->venue_id,
                'scheduled_venue_snapshot' => $lockedProposal->venue_snapshot,
            ]);
            app(CentralScheduleConflictService::class)->assertRequestScheduleAvailable($locked);
            $this->record($locked, $actor, 'AUTHORITY_SCHEDULE_CONFIRMED', $from, $locked->status, null, ['proposal_id' => $lockedProposal->id, 'proposal_type' => $lockedProposal->proposal_type]);
            $this->notifyUser($locked->created_by, 'زمان‌بندی رسمی مقام تأیید شد', 'زمان رسمی جلسه توسط منشی اجرائیه نهایی شد.', $locked);
            return $this->responseRequest($locked);
        });
    }

    private function durationMinutes(string $start, string $end): int
    {
        $startAt = strtotime($start);
        $endAt = strtotime($end);
        abort_unless($startAt !== false && $endAt !== false && $endAt > $startAt, 422, 'INVALID_SCHEDULE_TIME');
        return (int) (($endAt - $startAt) / 60);
    }
}
