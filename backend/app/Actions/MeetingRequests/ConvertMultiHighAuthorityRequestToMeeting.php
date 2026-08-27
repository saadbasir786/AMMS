<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConvertMultiHighAuthorityRequestToMeeting
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): Meeting
    {
        return DB::transaction(function () use ($actor, $request, $data): Meeting {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($actor->can('authority-meeting.finalize') && $context && $this->canScheduler($actor, $context->authority_office_unit_id), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::SCHEDULE_CONFIRMED->value, 422, 'AUTHORITY_SCHEDULE_NOT_CONFIRMED');
            abort_if($locked->meeting()->exists(), 422, 'MEETING_ALREADY_CREATED');
            abort_unless($this->allParticipatingApproved($locked) && $locked->requester_approved_at, 422, 'ALL_DIRECTORATE_APPROVALS_REQUIRED');
            abort_unless($context->office_review_status === 'COMPLETED' && $context->authority_decision_status === 'APPROVED', 422, 'HIGH_AUTHORITY_APPROVAL_REQUIRED');
            $holder = $this->persistedHolder($locked);
            $proposal = $locked->scheduleProposals()->where('status', 'ACCEPTED')->latest('id')->first();
            abort_unless($proposal, 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            $formalIds = $this->formalUnits($locked)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $ackIds = $locked->authorityScheduleUnitAcknowledgements()->where('meeting_schedule_proposal_id', $proposal->id)->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            abort_unless($ackIds->count() === $formalIds->count() && $ackIds->diff($formalIds)->isEmpty(), 422, 'ALL_DIRECTORATE_SCHEDULE_ACKNOWLEDGEMENTS_REQUIRED');
            $confirmedIds = $locked->participantConfirmations()->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            abort_unless($confirmedIds->count() === $formalIds->count() && $confirmedIds->diff($formalIds)->isEmpty(), 422, 'REQUESTER_PARTICIPANTS_NOT_CONFIRMED');
            abort_unless($locked->authorityParticipantConfirmations()->where('side', 'AUTHORITY')->exists(), 422, 'AUTHORITY_PARTICIPANTS_NOT_CONFIRMED');
            abort_unless($locked->schedule_confirmed_by && $locked->schedule_confirmed_at && $locked->scheduled_date && $locked->scheduled_start_time && $locked->scheduled_duration_minutes && $locked->scheduled_location, 422, 'AUTHORITY_SCHEDULE_NOT_CONFIRMED');

            $authorityNominations = $locked->nominations()->where('participant_stage', 'FINAL')->where('nomination_status', 'CONFIRMED')->where('nominated_by_unit_id', $context->authority_office_unit_id)->get();
            abort_unless($authorityNominations->contains('employee_id', $holder->id), 422, 'HIGH_AUTHORITY_PARTICIPANT_REQUIRED');
            $unitNominations = $locked->nominations()->where('participant_stage', 'FINAL')->where('nomination_status', 'CONFIRMED')->whereIn('nominated_by_unit_id', $formalIds->all())->get();
            foreach ($formalIds as $unitId) {
                abort_unless($unitNominations->where('nominated_by_unit_id', $unitId)->isNotEmpty(), 422, 'ALL_UNIT_PARTICIPANTS_REQUIRED');
            }
            $allParticipantIds = $unitNominations->pluck('employee_id')->concat($authorityNominations->pluck('employee_id'));
            abort_unless($allParticipantIds->unique()->count() === $allParticipantIds->count(), 422, 'DUPLICATE_FINAL_PARTICIPANT');

            $scheduledStart = substr((string) $locked->scheduled_start_time, 0, 5);
            $scheduledEnd = Carbon::createFromFormat('H:i', $scheduledStart)->addMinutes((int) $locked->scheduled_duration_minutes)->format('H:i');
            $meeting = Meeting::create([
                'meeting_request_id' => $locked->id,
                'meeting_number' => $this->nextNumber('meetings', 'MT'),
                'title' => $locked->subject,
                'description' => $locked->description,
                'organizing_unit_id' => $this->requesterUnit($locked)->id,
                'coordinator_id' => $locked->lead_coordinator_user_id,
                'chairperson_employee_id' => $holder->id,
                'meeting_date' => $locked->scheduled_date->format('Y-m-d'),
                'start_time' => $scheduledStart,
                'end_time' => $scheduledEnd,
                'location' => $locked->scheduled_location,
                'agenda' => $data['agenda'] ?? null,
                'status' => 'SCHEDULED',
            ]);
            $formalUnits = $this->formalUnits($locked);
            $meeting->units()->createMany($formalUnits->map(fn ($unit) => ['organizational_unit_id' => $unit->id, 'role' => $unit->id === $this->requesterUnit($locked)->id ? 'REQUESTING' : 'PARTICIPATING'])->all());
            foreach ($unitNominations as $nomination) $meeting->participants()->create(['employee_id' => $nomination->employee_id, 'organizational_unit_id' => $nomination->nominated_by_unit_id, 'role' => $nomination->nominated_by_unit_id === $this->requesterUnit($locked)->id ? 'REQUESTING' : 'PARTICIPATING']);
            foreach ($authorityNominations as $nomination) $meeting->participants()->create(['employee_id' => $nomination->employee_id, 'organizational_unit_id' => $nomination->organizational_unit_id, 'role' => $nomination->employee_id === $holder->id ? 'HIGH_AUTHORITY' : 'AUTHORITY_SUPPORT']);
            $meeting->authorityContext()->create(['organization_authority_id' => $context->organization_authority_id, 'authority_type' => $context->authority_type, 'authority_position_id' => $context->authority_position_id, 'authority_employee_id' => $holder->id, 'authority_office_unit_id' => $context->authority_office_unit_id]);
            app(\App\Support\ScheduleVersionService::class)->ensureInitialVersion($meeting, $actor->id);
            app(\App\Support\ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);
            $from = $locked->status;
            $locked->update(['status' => MeetingRequestStatus::CONVERTED_TO_MEETING->value]);
            $this->record($locked, $actor, 'MEETING_CREATED', $from, $locked->status, null, ['meeting_id' => $meeting->id, 'unit_ids' => $formalIds->all(), 'authority_employee_id' => $holder->id]);
            return $meeting->fresh(['request.units.unit', 'units.unit', 'participants.employee.unit', 'authorityContext.authorityEmployee', 'authorityContext.authorityPosition', 'authorityContext.authorityOfficeUnit', 'chairperson']);
        });
    }
}
