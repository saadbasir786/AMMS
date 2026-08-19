<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvertAuthorityInitiatedRequestToMeeting
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data = []): Meeting
    {
        return DB::transaction(function () use ($actor, $request, $data): Meeting {
            $locked = $this->lockFlow06Request($request);
            $context = $locked->authorityContext;
            $officeId = (int) ($context?->authority_office_unit_id ?? 0);
            abort_unless($actor->isActive() && $actor->can('authority-meeting.finalize') && $officeId && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeId)), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::SCHEDULE_CONFIRMED->value, 422, 'AUTHORITY_SCHEDULE_NOT_CONFIRMED');
            abort_if($locked->meeting()->exists(), 422, 'MEETING_ALREADY_CREATED');
            abort_unless($locked->requesting_unit_id === null, 422, 'REQUESTING_DIRECTORATE_NOT_ALLOWED');
            $version = $this->currentVersion($locked);
            abort_unless($version->version_status === 'ISSUED' && $this->allCurrentAcknowledged($locked), 422, 'ALL_DIRECTORATE_ACKNOWLEDGEMENTS_REQUIRED');
            $proposal = $locked->scheduleProposals()->where('status', 'ACCEPTED')->latest('id')->first();
            abort_unless($proposal, 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            $unitIds = $this->flow06Units($locked)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $scheduleAckIds = $locked->authorityScheduleUnitAcknowledgements()->where('meeting_schedule_proposal_id', $proposal->id)->whereIn('organizational_unit_id', $unitIds)->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            abort_unless($scheduleAckIds->count() === $unitIds->count() && $scheduleAckIds->diff($unitIds)->isEmpty(), 422, 'AUTHORITY_SCHEDULE_NOT_ACKNOWLEDGED_BY_ALL');
            $confirmedIds = $locked->participantConfirmations()->whereIn('organizational_unit_id', $unitIds)->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            abort_unless($confirmedIds->count() === $unitIds->count() && $confirmedIds->diff($unitIds)->isEmpty(), 422, 'ALL_DIRECTORATE_PARTICIPANTS_REQUIRED');
            abort_unless($locked->authorityParticipantConfirmations()->where('side', 'AUTHORITY')->exists(), 422, 'AUTHORITY_PARTICIPANTS_NOT_CONFIRMED');
            $holder = $this->currentAuthorityHolder($context->organizationAuthority);
            $authorityNominations = $locked->nominations()->where('participant_stage', 'FINAL')->where('nomination_status', 'CONFIRMED')->where('nominated_by_unit_id', $officeId)->get();
            abort_unless($authorityNominations->contains('employee_id', $holder->id), 422, 'HIGH_AUTHORITY_PARTICIPANT_REQUIRED');
            abort_unless(filled($proposal->meeting_date) && filled($proposal->start_time) && (string) $proposal->end_time > (string) $proposal->start_time && filled($proposal->location), 422, 'INVALID_SCHEDULE');

            $meeting = Meeting::create([
                'meeting_request_id' => $locked->id, 'meeting_number' => $this->nextNumber('meetings', 'MT'),
                'title' => $locked->subject, 'description' => $locked->description,
                'organizing_unit_id' => $officeId, 'coordinator_id' => $locked->lead_coordinator_user_id ?: $actor->id,
                'chairperson_employee_id' => $holder->id, 'meeting_date' => $proposal->meeting_date,
                'start_time' => $proposal->start_time, 'end_time' => $proposal->end_time, 'location' => $proposal->location,
                'agenda' => $data['agenda'] ?? $locked->proposed_agenda, 'status' => 'SCHEDULED',
            ]);
            $formalUnits = $this->flow06Units($locked);
            $meeting->units()->createMany($formalUnits->map(fn ($unit) => ['organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING'])->all());
            $unitNominations = $locked->nominations()->where('participant_stage', 'FINAL')->where('nomination_status', 'CONFIRMED')->whereIn('nominated_by_unit_id', $unitIds->all())->get();
            foreach ($unitNominations as $nomination) $meeting->participants()->create(['employee_id' => $nomination->employee_id, 'organizational_unit_id' => $nomination->nominated_by_unit_id, 'role' => 'PARTICIPATING']);
            foreach ($authorityNominations as $nomination) $meeting->participants()->create(['employee_id' => $nomination->employee_id, 'organizational_unit_id' => $nomination->organizational_unit_id, 'role' => $nomination->employee_id === $holder->id ? 'HIGH_AUTHORITY' : 'AUTHORITY_SUPPORT']);
            $meeting->authorityContext()->create(['organization_authority_id' => $context->organization_authority_id, 'authority_type' => $context->authority_type, 'authority_position_id' => $context->authority_position_id, 'authority_employee_id' => $holder->id, 'authority_office_unit_id' => $context->authority_office_unit_id]);
            $from = $locked->status;
            $locked->update(['status' => MeetingRequestStatus::CONVERTED_TO_MEETING->value]);
            $this->record06($locked, $actor, 'MEETING_CREATED', $from, MeetingRequestStatus::CONVERTED_TO_MEETING->value, null, ['meeting_id' => $meeting->id, 'unit_ids' => $unitIds->all(), 'authority_employee_id' => $holder->id, 'directive_version' => $version->version]);
            return $meeting->fresh(['request.units.unit', 'request.currentDirectiveVersion', 'units.unit', 'participants.employee.unit', 'authorityContext.authorityEmployee', 'authorityContext.authorityPosition', 'authorityContext.authorityOfficeUnit', 'chairperson']);
        });
    }
}
