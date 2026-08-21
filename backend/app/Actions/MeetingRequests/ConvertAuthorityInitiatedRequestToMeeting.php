<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConvertAuthorityInitiatedRequestToMeeting
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data = []): Meeting
    {
        return DB::transaction(function () use ($actor, $request, $data): Meeting {
            $locked = $this->lockFlow06Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            $officeId = (int) ($context?->authority_office_unit_id ?? 0);
            abort_unless($actor->can('authority-meeting.finalize') && $officeId && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeId), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::SCHEDULE_CONFIRMED->value, 422, 'AUTHORITY_SCHEDULE_NOT_CONFIRMED');
            abort_if($locked->meeting()->exists(), 422, 'MEETING_ALREADY_CREATED');
            abort_unless($locked->requesting_unit_id === null, 422, 'REQUESTING_DIRECTORATE_NOT_ALLOWED');

            $version = $this->currentVersion($locked);
            abort_unless($version->version_status === 'ISSUED' && $this->allCurrentAcknowledged($locked), 422, 'ALL_DIRECTORATE_ACKNOWLEDGEMENTS_REQUIRED');
            abort_unless($context && $context->authority_employee_id && (int) $version->authority_employee_id === (int) $context->authority_employee_id, 422, 'HIGH_AUTHORITY_PARTICIPANT_UNAVAILABLE');
            $holder = Employee::with(['position', 'unit'])->find($context->authority_employee_id);
            abort_unless($holder && $holder->status === 'ACTIVE' && (int) $holder->position_id === (int) $context->authority_position_id && $holder->position?->status === 'ACTIVE' && $holder->unit?->status === 'ACTIVE', 422, 'HIGH_AUTHORITY_PARTICIPANT_UNAVAILABLE');

            $proposal = $locked->scheduleProposals()->where('status', 'ACCEPTED')->latest('id')->first();
            abort_unless($proposal, 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            abort_unless($locked->schedule_confirmed_by && $locked->schedule_confirmed_at && $locked->scheduled_date && $locked->scheduled_start_time && $locked->scheduled_duration_minutes && $locked->scheduled_location, 422, 'AUTHORITY_SCHEDULE_NOT_CONFIRMED');

            $unitIds = $this->flow06Units($locked)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $scheduleAckIds = $locked->authorityScheduleUnitAcknowledgements()->where('meeting_schedule_proposal_id', $proposal->id)->whereIn('organizational_unit_id', $unitIds)->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            abort_unless($scheduleAckIds->count() === $unitIds->count() && $scheduleAckIds->diff($unitIds)->isEmpty(), 422, 'AUTHORITY_SCHEDULE_NOT_ACKNOWLEDGED_BY_ALL');
            $confirmedIds = $locked->participantConfirmations()->whereIn('organizational_unit_id', $unitIds)->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            abort_unless($confirmedIds->count() === $unitIds->count() && $confirmedIds->diff($unitIds)->isEmpty(), 422, 'ALL_DIRECTORATE_PARTICIPANTS_REQUIRED');
            abort_unless($locked->authorityParticipantConfirmations()->where('side', 'AUTHORITY')->exists(), 422, 'AUTHORITY_PARTICIPANTS_NOT_CONFIRMED');

            $authorityNominations = $locked->nominations()->where('participant_stage', 'FINAL')->where('nomination_status', 'CONFIRMED')->where('nominated_by_unit_id', $officeId)->get();
            abort_unless($authorityNominations->contains('employee_id', $holder->id), 422, 'HIGH_AUTHORITY_PARTICIPANT_REQUIRED');
            $unitNominations = $locked->nominations()->where('participant_stage', 'FINAL')->where('nomination_status', 'CONFIRMED')->whereIn('nominated_by_unit_id', $unitIds->all())->get();
            foreach ($unitIds as $unitId) {
                abort_unless($unitNominations->where('nominated_by_unit_id', $unitId)->isNotEmpty(), 422, 'ALL_DIRECTORATE_PARTICIPANTS_REQUIRED');
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
                'organizing_unit_id' => $officeId,
                'coordinator_id' => $actor->id,
                'chairperson_employee_id' => $holder->id,
                'meeting_date' => $locked->scheduled_date->format('Y-m-d'),
                'start_time' => $scheduledStart,
                'end_time' => $scheduledEnd,
                'location' => $locked->scheduled_location,
                'agenda' => $data['agenda'] ?? $locked->proposed_agenda,
                'status' => 'SCHEDULED',
            ]);
            $formalUnits = $this->flow06Units($locked);
            $meeting->units()->createMany($formalUnits->map(fn ($unit) => ['organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING'])->all());
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