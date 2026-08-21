<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingRescheduleSupport;
use App\Models\Meeting;
use App\Models\MeetingRescheduleAlternativeRequest;
use App\Models\MeetingRescheduleCase;
use App\Models\MeetingRescheduleProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RequestMeetingScheduleAlternative
{
    use MeetingRescheduleSupport;

    public function __invoke(User $actor, Meeting $meeting, MeetingRescheduleCase $case, MeetingRescheduleProposal $proposal, array $data): MeetingRescheduleAlternativeRequest
    {
        return DB::transaction(function () use ($actor, $meeting, $case, $proposal, $data): MeetingRescheduleAlternativeRequest {
            $locked = $this->lockMeeting($meeting);
            $this->ensureReschedulable($locked);
            $case = $case->fresh();
            $proposal = $proposal->fresh();
            $this->ensureCaseActive($case);
            abort_unless((int) $proposal->meeting_id === (int) $locked->id && (int) $proposal->reschedule_case_id === (int) $case->id && $proposal->status === 'ACTIVE', 422, 'RESCHEDULE_PROPOSAL_NOT_ACTIVE');
            $governance = $this->resolver($locked);
            abort_unless($actor->isActive() && $actor->can('meeting-reschedule.request-alternative'), 403, 'UNAUTHORIZED_RESCHEDULE_ACKNOWLEDGEMENT');
            $unitId = $this->actorFormalUnitId($actor, $governance['required_unit_ids'], ['EXECUTIVE_MANAGEMENT', 'DIRECTOR']);
            abort_unless($unitId, 403, 'UNAUTHORIZED_RESCHEDULE_ACKNOWLEDGEMENT');
            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') throw new HttpException(422, 'RESCHEDULE_REASON_REQUIRED');
            abort_if($proposal->alternativeRequests()->where('organizational_unit_id', $unitId)->where('status', 'OPEN')->exists(), 422, 'ALTERNATIVE_ALREADY_OPEN');
            $alternative = MeetingRescheduleAlternativeRequest::create([
                'reschedule_case_id' => $case->id,
                'proposal_id' => $proposal->id,
                'organizational_unit_id' => $unitId,
                'requested_by_user_id' => $actor->id,
                'reason' => $reason,
                'suggested_date' => $data['suggested_date'] ?? null,
                'suggested_start_time' => $data['suggested_start_time'] ?? null,
                'suggested_end_time' => $data['suggested_end_time'] ?? null,
                'status' => 'OPEN',
            ]);
            $case->update(['status' => 'NEGOTIATING']);
            $this->recordMeetingAction($locked, $actor, 'RESCHEDULE_ALTERNATIVE_REQUESTED', $reason, [
                'case_id' => $case->id, 'proposal_id' => $proposal->id, 'alternative_request_id' => $alternative->id, 'organizational_unit_id' => $unitId,
            ]);
            if ($governance['authority_controlled']) {
                $this->notifyUsers($this->authoritySchedulerIds($governance['authority_office_unit_id']), 'MEETING_RESCHEDULE', 'درخواست زمان بدیل', 'یک ریاست برای پیشنهاد رسمی زمان بدیل درخواست کرده است.', ['meeting_id' => $locked->id, 'case_id' => $case->id, 'proposal_id' => $proposal->id, 'alternative_request_id' => $alternative->id]);
            } else {
                $this->notifyFormalUnits($locked, $governance['required_unit_ids'], 'MEETING_RESCHEDULE', 'درخواست زمان بدیل', 'یکی از ریاست‌های رسمی برای زمان جلسه درخواست بدیل ثبت کرده است.', ['case_id' => $case->id, 'proposal_id' => $proposal->id, 'alternative_request_id' => $alternative->id]);
            }
            return $alternative->fresh(['unit', 'requestedBy']);
        });
    }
}
