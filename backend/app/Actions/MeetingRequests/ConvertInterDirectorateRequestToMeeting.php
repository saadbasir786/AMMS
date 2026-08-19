<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\User;
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
            $requester = $this->unitFor($locked, 'REQUESTER');
            $target = $this->unitFor($locked, 'TARGET');
            abort_unless($this->canCoordinateUnit($actor, $requester->id) || $this->canCoordinateUnit($actor, $target->id), 403, 'FORBIDDEN');
            $chairperson = Employee::with('unit')->findOrFail($data['chairperson_employee_id']);
            abort_unless($chairperson->status === 'ACTIVE', 422, 'INVALID_CHAIRPERSON');
            abort_unless($requester->isWithinScope($chairperson->organizational_unit_id) || $target->isWithinScope($chairperson->organizational_unit_id), 422, 'INVALID_CHAIRPERSON');
            abort_unless(filled($data['location'] ?? $locked->proposed_location), 422, 'VALIDATION_ERROR');
            $nominations = $locked->nominations()->where('participant_stage', 'FINAL')->where('nomination_status', 'CONFIRMED')->get();
            abort_unless($nominations->where('nominated_by_unit_id', $requester->id)->isNotEmpty(), 422, 'REQUESTER_PARTICIPANTS_NOT_CONFIRMED');
            abort_unless($nominations->where('nominated_by_unit_id', $target->id)->isNotEmpty(), 422, 'TARGET_PARTICIPANTS_NOT_CONFIRMED');
            $meeting = Meeting::create([
                'meeting_request_id' => $locked->id,
                'meeting_number' => $this->nextNumber('meetings', 'MT'),
                'title' => $locked->subject,
                'description' => $locked->description,
                'organizing_unit_id' => $requester->id,
                'coordinator_id' => $locked->lead_coordinator_user_id,
                'chairperson_employee_id' => $chairperson->id,
                'meeting_date' => $locked->preferred_date,
                'start_time' => $locked->preferred_start_time,
                'end_time' => $data['end_time'] ?? $this->endTime($locked->preferred_start_time, $locked->estimated_duration_minutes),
                'location' => $data['location'] ?? $locked->proposed_location,
                'agenda' => $data['agenda'] ?? null,
                'status' => 'SCHEDULED',
            ]);
            $meeting->units()->createMany([
                ['organizational_unit_id' => $requester->id, 'role' => 'REQUESTING'],
                ['organizational_unit_id' => $target->id, 'role' => 'TARGET'],
            ]);
            foreach ($nominations as $nomination) {
                $meeting->participants()->create(['employee_id' => $nomination->employee_id, 'organizational_unit_id' => $nomination->nominated_by_unit_id, 'role' => $nomination->nominated_by_unit_id === $requester->id ? 'REQUESTING' : 'TARGET']);
            }
            $from = $locked->status;
            $locked->update(['status' => MeetingRequestStatus::CONVERTED_TO_MEETING->value]);
            $this->record($locked, $actor, 'MEETING_CREATED', $from, MeetingRequestStatus::CONVERTED_TO_MEETING->value);
            $recipientIds = User::whereIn('employee_id', $nominations->pluck('employee_id')->all())->pluck('id')->push($locked->lead_coordinator_user_id)->push($data['target_coordinator_user_id'] ?? null)->filter()->unique();
            foreach ($recipientIds as $recipientId) {
                $this->notifyUser((int) $recipientId, 'جلسهٔ بین‌ریاستی ایجاد شد', 'جلسه پس از تکمیل تأییدها و زمان‌بندی ایجاد و نهایی شد.', $locked);
            }
            return $meeting->fresh(['request.units.unit', 'units.unit', 'participants.employee', 'chairperson']);
        });
    }

    private function endTime(?string $start, ?int $duration): string
    {
        abort_unless($start && $duration, 422, 'SCHEDULE_NOT_CONFIRMED');
        return date('H:i:s', strtotime($start) + ($duration * 60));
    }
}
