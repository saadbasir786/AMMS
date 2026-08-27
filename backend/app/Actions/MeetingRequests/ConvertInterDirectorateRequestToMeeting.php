<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConvertInterDirectorateRequestToMeeting
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): Meeting
    {
        return DB::transaction(function () use ($actor, $request, $data): Meeting {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting.create'), 403, 'FORBIDDEN');
            $locked = $this->lockFlow02Request($request);
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::SCHEDULE_CONFIRMED, 422, 'SCHEDULE_NOT_CONFIRMED');
            abort_unless($locked->requester_approved_by && $locked->target_approved_by, 422, 'BILATERAL_APPROVAL_REQUIRED');
            abort_unless($locked->lead_coordinator_unit_id && $locked->lead_coordinator_user_id, 422, 'LEAD_COORDINATOR_REQUIRED');
            abort_unless($locked->requester_participants_confirmed_at, 422, 'REQUESTER_PARTICIPANTS_NOT_CONFIRMED');
            abort_unless($locked->target_participants_confirmed_at, 422, 'TARGET_PARTICIPANTS_NOT_CONFIRMED');
            abort_unless(!$locked->meeting()->exists(), 422, 'MEETING_ALREADY_CREATED');
            abort_unless($locked->scheduled_date && $locked->scheduled_start_time && $locked->scheduled_duration_minutes && $locked->scheduled_location, 422, 'SCHEDULE_NOT_CONFIRMED');

            $requester = $this->unitFor($locked, 'REQUESTER');
            $target = $this->unitFor($locked, 'TARGET');
            abort_unless($this->canCoordinateUnit($actor, $requester->id), 403, 'REQUESTER_LEAD_REQUIRED');

            $chairperson = Employee::with('unit')->findOrFail($data['chairperson_employee_id']);
            abort_unless($chairperson->status === 'ACTIVE', 422, 'INVALID_CHAIRPERSON');
            abort_unless($requester->isWithinScope($chairperson->organizational_unit_id) || $target->isWithinScope($chairperson->organizational_unit_id), 422, 'INVALID_CHAIRPERSON');

            $nominations = $locked->nominations()->where('participant_stage', 'FINAL')->where('nomination_status', 'CONFIRMED')->get();
            abort_unless($nominations->where('nominated_by_unit_id', $requester->id)->isNotEmpty(), 422, 'REQUESTER_PARTICIPANTS_NOT_CONFIRMED');
            abort_unless($nominations->where('nominated_by_unit_id', $target->id)->isNotEmpty(), 422, 'TARGET_PARTICIPANTS_NOT_CONFIRMED');

            $scheduledStart = substr((string) $locked->scheduled_start_time, 0, 5);
            $scheduledEnd = Carbon::createFromFormat('H:i', $scheduledStart)
                ->addMinutes((int) $locked->scheduled_duration_minutes)
                ->format('H:i');

            $meeting = Meeting::create([
                'meeting_request_id' => $locked->id,
                'meeting_number' => $this->nextNumber('meetings', 'MT'),
                'title' => $locked->subject,
                'description' => $locked->description,
                'organizing_unit_id' => $requester->id,
                'coordinator_id' => $locked->lead_coordinator_user_id,
                'chairperson_employee_id' => $chairperson->id,
                'meeting_date' => $locked->scheduled_date->format('Y-m-d'),
                'start_time' => $scheduledStart,
                'end_time' => $scheduledEnd,
                'location' => $locked->scheduled_location,
                'agenda' => $data['agenda'] ?? null,
                'status' => 'SCHEDULED',
            ]);
            $meeting->units()->createMany([
                ['organizational_unit_id' => $requester->id, 'role' => 'REQUESTING'],
                ['organizational_unit_id' => $target->id, 'role' => 'PARTICIPATING'],
            ]);
            foreach ($nominations as $nomination) {
                $meeting->participants()->create([
                    'employee_id' => $nomination->employee_id,
                    'organizational_unit_id' => $nomination->nominated_by_unit_id,
                    'role' => $nomination->nominated_by_unit_id === $requester->id ? 'REQUESTING' : 'TARGET',
                ]);
            }
            app(\App\Support\ScheduleVersionService::class)->ensureInitialVersion($meeting, $actor->id);
            app(\App\Support\ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);
            $from = $locked->status;
            $locked->update(['status' => MeetingRequestStatus::CONVERTED_TO_MEETING->value]);
            $this->record($locked, $actor, 'MEETING_CREATED', $from, MeetingRequestStatus::CONVERTED_TO_MEETING->value);
            $recipientIds = User::whereIn('employee_id', $nominations->pluck('employee_id')->all())
                ->pluck('id')
                ->push($locked->lead_coordinator_user_id)
                ->push($data['target_coordinator_user_id'] ?? null)
                ->filter()
                ->unique();
            foreach ($recipientIds as $recipientId) {
                $this->notifyUser((int) $recipientId, 'جلسهٔ بین‌ریاستی ایجاد شد', 'جلسه پس از تکمیل تأییدها و زمان‌بندی ایجاد و نهایی شد.', $locked);
            }
            return $meeting->fresh(['request.units.unit', 'units.unit', 'participants.employee', 'chairperson']);
        });
    }
}
