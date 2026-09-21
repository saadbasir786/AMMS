<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\CentralScheduleConflictService;

class ConvertMultiDirectorateRequestToMeeting
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): Meeting
    {
        return DB::transaction(function () use ($actor, $request, $data): Meeting {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting.create'), 403, 'FORBIDDEN');
            $locked = $this->lockMultiDirectorateRequest($request);
            abort_unless($locked->status === MeetingRequestStatus::SCHEDULE_CONFIRMED->value, 422, 'SCHEDULE_NOT_CONFIRMED');
            abort_unless($locked->lead_coordinator_unit_id && $locked->lead_coordinator_user_id, 422, 'LEAD_COORDINATOR_REQUIRED');
            abort_unless($this->canCoordinateUnit($actor, $locked->lead_coordinator_unit_id), 403, 'FORBIDDEN');
            abort_unless($this->allTargetsApproved($locked), 422, 'ALL_TARGET_APPROVALS_REQUIRED');
            abort_if($locked->meeting()->exists(), 422, 'MEETING_ALREADY_CREATED');
            abort_unless($locked->scheduled_date && $locked->scheduled_start_time && $locked->scheduled_duration_minutes && $locked->scheduled_location, 422, 'SCHEDULE_NOT_CONFIRMED');

            $units = $this->participatingUnits($locked);
            $proposal = $locked->scheduleProposals->firstWhere('status', 'ACCEPTED');
            abort_unless($proposal, 422, 'SCHEDULE_NOT_ACCEPTED_BY_ALL_UNITS');
            $acceptedIds = $proposal->acceptances->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->unique();
            $unitIds = $units->pluck('id')->map(fn ($id) => (int) $id);
            abort_unless($acceptedIds->count() === $unitIds->count() && $acceptedIds->diff($unitIds)->isEmpty(), 422, 'SCHEDULE_NOT_ACCEPTED_BY_ALL_UNITS');

            $confirmedIds = $locked->participantConfirmations->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->unique();
            abort_unless($confirmedIds->count() === $unitIds->count() && $confirmedIds->diff($unitIds)->isEmpty(), 422, 'UNIT_PARTICIPANTS_NOT_CONFIRMED');

            $chairperson = Employee::with('unit')->findOrFail((int) $data['chairperson_employee_id']);
            abort_unless($chairperson->status === 'ACTIVE', 422, 'INVALID_CHAIRPERSON');
            abort_unless($units->contains(fn ($unit) => $unit->isWithinScope($chairperson->organizational_unit_id)), 422, 'INVALID_CHAIRPERSON');

            $nominations = $locked->nominations()->where('participant_stage', 'FINAL')->where('nomination_status', 'CONFIRMED')->get();
            foreach ($units as $unit) {
                abort_unless($nominations->where('nominated_by_unit_id', $unit->id)->isNotEmpty(), 422, 'ALL_UNIT_PARTICIPANTS_REQUIRED');
            }

            $scheduledStart = substr((string) $locked->scheduled_start_time, 0, 5);
            $scheduledEnd = Carbon::createFromFormat('H:i', $scheduledStart)
                ->addMinutes((int) $locked->scheduled_duration_minutes)
                ->format('H:i');

            $meeting = Meeting::create([
                'meeting_request_id' => $locked->id,
                'meeting_number' => $this->nextNumber('meetings', 'MT'),
                'title' => $locked->subject,
                'description' => $locked->description,
                'organizing_unit_id' => $this->requesterUnit($locked)->id,
                'coordinator_id' => $locked->lead_coordinator_user_id,
                'chairperson_employee_id' => $chairperson->id,
                'meeting_date' => $locked->scheduled_date->format('Y-m-d'),
                'start_time' => $scheduledStart,
                'end_time' => $scheduledEnd,
                'location' => $locked->scheduled_location,
                'venue_type' => $locked->scheduled_venue_type ?: 'EXTERNAL_LOCATION',
                'venue_id' => $locked->scheduled_venue_id,
                'venue_snapshot' => $locked->scheduled_venue_snapshot,
                'agenda' => $data['agenda'] ?? null,
                'status' => 'SCHEDULED',
            ]);

            $meeting->units()->createMany($units->map(fn ($unit) => [
                'organizational_unit_id' => $unit->id,
                'role' => $unit->id === $this->requesterUnit($locked)->id ? 'REQUESTING' : 'PARTICIPATING',
            ])->all());

            foreach ($nominations as $nomination) {
                $meeting->participants()->create([
                    'employee_id' => $nomination->employee_id,
                    'organizational_unit_id' => $nomination->nominated_by_unit_id,
                    'role' => $nomination->nominated_by_unit_id === $this->requesterUnit($locked)->id ? 'REQUESTING' : 'PARTICIPATING',
                ]);
            }
            app(\App\Support\ScheduleVersionService::class)->ensureInitialVersion($meeting, $actor->id);
            app(\App\Support\ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);
            $conflicts = app(CentralScheduleConflictService::class)->evaluateMeeting($meeting->fresh(['units.unit', 'organizingUnit', 'participants', 'authorityContext']), [
                'meeting_date' => $meeting->meeting_date->toDateString(), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time,
                'location' => $meeting->location, 'venue_type' => $meeting->venue_type, 'venue_id' => $meeting->venue_id,
            ], true);
            app(CentralScheduleConflictService::class)->assertNoBlocking($conflicts);

            $from = $locked->status;
            $locked->update(['status' => MeetingRequestStatus::CONVERTED_TO_MEETING->value]);
            $this->record($locked, $actor, 'MEETING_CREATED', $from, $locked->status, null, ['meeting_id' => $meeting->id, 'unit_ids' => $unitIds->all(), 'schedule_warnings' => $conflicts['warnings']]);
            $recipientIds = User::whereIn('employee_id', $nominations->pluck('employee_id')->all())->pluck('id')->push($locked->lead_coordinator_user_id)->unique();
            foreach ($recipientIds as $recipientId) {
                $this->notifyUser((int) $recipientId, 'جلسهٔ چندریاستی ایجاد شد', 'جلسه پس از تکمیل تأیید همهٔ ریاست‌ها ایجاد و نهایی شد.', $locked);
            }

            return $meeting->fresh(['request.units.unit', 'units.unit', 'participants.employee', 'chairperson']);
        });
    }
}
