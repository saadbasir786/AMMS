<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\MeetingAuthorityParticipantConfirmation;
use App\Models\MeetingAuthorityScheduleAcknowledgement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConvertHighAuthorityRequestToMeeting
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data = []): Meeting
    {
        return DB::transaction(function () use ($actor, $request, $data): Meeting {
            $locked = $this->lockHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context, 422, 'HIGH_AUTHORITY_NOT_CONFIGURED');
            abort_unless($this->canAuthorityScheduler($actor, $context->authority_office_unit_id) && $actor->can('authority-meeting.finalize'), 403, 'UNAUTHORIZED_AUTHORITY_SCHEDULER');
            abort_unless($locked->status === MeetingRequestStatus::SCHEDULE_CONFIRMED->value, 422, 'AUTHORITY_SCHEDULE_NOT_CONFIRMED');
            abort_if($locked->meeting()->exists(), 422, 'MEETING_ALREADY_CREATED');

            $authority = $this->activeAuthorityFor($locked);
            abort_unless($context->organization_authority_id === $authority->id, 422, 'HIGH_AUTHORITY_PARTICIPANT_UNAVAILABLE');
            $holder = $this->assertPersistedHolderAvailable($locked);
            $proposal = $locked->scheduleProposals()->whereIn('proposal_type', ['AUTHORITY_OFFICIAL_PROPOSAL', 'AUTHORITY_REVISED_PROPOSAL'])->where('status', 'ACCEPTED')->latest('id')->first();
            abort_unless($proposal, 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            abort_unless(MeetingAuthorityScheduleAcknowledgement::where('meeting_schedule_proposal_id', $proposal->id)->where('requesting_unit_id', $locked->requesting_unit_id)->exists(), 422, 'AUTHORITY_SCHEDULE_NOT_ACKNOWLEDGED');
            abort_unless($locked->schedule_confirmed_by && $locked->schedule_confirmed_at && $locked->scheduled_date && $locked->scheduled_start_time && $locked->scheduled_duration_minutes && $locked->scheduled_location, 422, 'AUTHORITY_SCHEDULE_NOT_CONFIRMED');
            abort_unless(MeetingAuthorityParticipantConfirmation::where('meeting_request_id', $locked->id)->where('side', 'REQUESTER')->exists(), 422, 'REQUESTER_PARTICIPANTS_NOT_CONFIRMED');
            abort_unless(MeetingAuthorityParticipantConfirmation::where('meeting_request_id', $locked->id)->where('side', 'AUTHORITY')->exists(), 422, 'AUTHORITY_PARTICIPANTS_NOT_CONFIRMED');

            $requesterNominations = $locked->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $locked->requesting_unit_id)->get();
            $authorityNominations = $locked->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $context->authority_office_unit_id)->get();
            abort_unless($requesterNominations->isNotEmpty(), 422, 'REQUESTER_PARTICIPANTS_NOT_CONFIRMED');
            abort_unless($authorityNominations->contains('employee_id', $holder->id), 422, 'HIGH_AUTHORITY_PARTICIPANT_REQUIRED');
            $allNominations = $requesterNominations->concat($authorityNominations);
            abort_unless($allNominations->pluck('employee_id')->unique()->count() === $allNominations->count(), 422, 'DUPLICATE_FINAL_PARTICIPANT');

            $scheduledStart = substr((string) $locked->scheduled_start_time, 0, 5);
            $scheduledEnd = Carbon::createFromFormat('H:i', $scheduledStart)->addMinutes((int) $locked->scheduled_duration_minutes)->format('H:i');
            $meeting = Meeting::create([
                'meeting_request_id' => $locked->id,
                'meeting_number' => $this->nextNumber('meetings', 'MT'),
                'title' => $locked->subject,
                'description' => $locked->description,
                'organizing_unit_id' => $locked->requesting_unit_id,
                'coordinator_id' => $actor->id,
                'chairperson_employee_id' => $holder->id,
                'meeting_date' => $locked->scheduled_date->format('Y-m-d'),
                'start_time' => $scheduledStart,
                'end_time' => $scheduledEnd,
                'location' => $locked->scheduled_location,
                'agenda' => $data['agenda'] ?? null,
                'status' => 'SCHEDULED',
            ]);
            $meeting->units()->create(['organizational_unit_id' => $locked->requesting_unit_id, 'role' => 'REQUESTING']);
            foreach ($requesterNominations as $nomination) {
                $meeting->participants()->create(['employee_id' => $nomination->employee_id, 'organizational_unit_id' => $nomination->organizational_unit_id, 'role' => 'REQUESTING']);
            }
            foreach ($authorityNominations as $nomination) {
                $meeting->participants()->create(['employee_id' => $nomination->employee_id, 'organizational_unit_id' => $nomination->organizational_unit_id, 'role' => $nomination->employee_id === $holder->id ? 'HIGH_AUTHORITY' : 'AUTHORITY_SUPPORT']);
            }
            $meeting->authorityContext()->create([
                'organization_authority_id' => $context->organization_authority_id,
                'authority_type' => $context->authority_type,
                'authority_position_id' => $context->authority_position_id,
                'authority_employee_id' => $holder->id,
                'authority_office_unit_id' => $context->authority_office_unit_id,
            ]);
            app(\App\Support\ScheduleVersionService::class)->ensureInitialVersion($meeting, $actor->id);
            app(\App\Support\ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);

            $from = $this->updateStatus($locked, MeetingRequestStatus::CONVERTED_TO_MEETING);
            $this->record($locked, $actor, 'MEETING_CREATED', $from, $locked->status, null, ['meeting_id' => $meeting->id, 'authority_employee_id' => $holder->id, 'requesting_unit_id' => $locked->requesting_unit_id]);
            $recipientIds = User::whereIn('employee_id', $allNominations->pluck('employee_id')->all())->pluck('id')->unique();
            foreach ($recipientIds as $recipientId) $this->notifyUser((int) $recipientId, 'جلسه با مقام عالی ایجاد شد', 'جلسهٔ رسمی پس از تأیید مقام عالی و نهایی‌شدن زمان‌بندی ایجاد شد.', $locked);
            $this->notifyUsersForUnit($context->authority_office_unit_id, ['EXECUTIVE_SECRETARY'], 'جلسه با مقام عالی نهایی شد', 'جلسهٔ رسمی در تقویم مقام عالی ثبت شد.', $locked);

            return $meeting->fresh(['request.requestingUnit', 'authorityContext.organizationAuthority', 'authorityContext.authorityPosition', 'authorityContext.authorityEmployee', 'authorityContext.authorityOfficeUnit', 'units.unit', 'participants.employee.unit', 'chairperson']);
        });
    }
}
