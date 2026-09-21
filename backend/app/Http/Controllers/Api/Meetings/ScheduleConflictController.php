<?php

namespace App\Http\Controllers\Api\Meetings;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\Employee;
use App\Models\OrganizationalUnit;
use App\Support\CentralScheduleConflictService;
use App\Support\ScheduleVersionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ScheduleConflictController
{
    public function request(Request $httpRequest, MeetingRequest $meetingRequest, CentralScheduleConflictService $conflicts, ScheduleVersionService $versions)
    {
        Gate::authorize('scheduleConflictPreview', $meetingRequest);
        $data = $httpRequest->validate([
            'meeting_date' => ['nullable', 'date'], 'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'], 'venue_type' => ['nullable', 'in:INTERNAL_VENUE,EXTERNAL_LOCATION,ONLINE'],
            'venue_id' => ['nullable', 'integer'], 'location' => ['nullable', 'string', 'max:255'],
            'chairperson_employee_id' => ['nullable', 'integer'], 'participant_ids' => ['nullable', 'array'], 'participant_ids.*' => ['integer'],
        ]);
        $proposal = $meetingRequest->scheduleProposals()->whereIn('status', ['ACCEPTED', 'PROPOSED', 'COUNTER_PROPOSED'])->latest('id')->first();
        $date = $data['meeting_date'] ?? $meetingRequest->scheduled_date?->toDateString() ?? $proposal?->meeting_date?->toDateString();
        $start = $data['start_time'] ?? $meetingRequest->scheduled_start_time ?? $proposal?->start_time;
        $end = $data['end_time'] ?? null;
        if (!$end && $start && $meetingRequest->scheduled_duration_minutes) {
            $end = \Carbon\Carbon::createFromFormat('H:i', substr((string) $start, 0, 5))->addMinutes((int) $meetingRequest->scheduled_duration_minutes)->format('H:i');
        }
        $end ??= $proposal?->end_time;
        $chairpersonId = (int) ($data['chairperson_employee_id'] ?? $meetingRequest->scheduled_chairperson_employee_id ?? 0);
        $this->assertEmployeeScope($meetingRequest, $chairpersonId, $data['participant_ids'] ?? null);
        if (!$date || !$start || !$end || !$chairpersonId) return response()->json(['success' => true, 'data' => ['blocking_conflicts' => [], 'warnings' => [], 'evaluated' => false]]);

        $schedule = $versions->normalize([
            'meeting_date' => $date, 'start_time' => substr((string) $start, 0, 5), 'end_time' => substr((string) $end, 0, 5),
            'location' => $data['location'] ?? $meetingRequest->scheduled_location ?? $proposal?->location,
            'venue_type' => $data['venue_type'] ?? $meetingRequest->scheduled_venue_type ?? $proposal?->venue_type,
            'venue_id' => $data['venue_id'] ?? $meetingRequest->scheduled_venue_id ?? $proposal?->venue_id,
        ], $meetingRequest);
        $participantIds = $data['participant_ids'] ?? $meetingRequest->participants()->whereIn('participant_type', ['FINAL', 'PROPOSED'])->pluck('employee_id')->all();
        $result = $conflicts->evaluateRequest($meetingRequest, $schedule, $chairpersonId, $participantIds, (int) ($meetingRequest->authorityContext?->authority_employee_id ?? 0));

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function meeting(Request $httpRequest, Meeting $meeting, CentralScheduleConflictService $conflicts, ScheduleVersionService $versions)
    {
        Gate::authorize('meetingScheduleConflictPreview', $meeting);
        $data = $httpRequest->validate([
            'meeting_date' => ['required', 'date'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i'],
            'venue_type' => ['nullable', 'in:INTERNAL_VENUE,EXTERNAL_LOCATION,ONLINE'], 'venue_id' => ['nullable', 'integer'], 'location' => ['nullable', 'string', 'max:255'],
        ]);
        $schedule = $versions->normalize(array_merge($data, ['location' => $data['location'] ?? $meeting->location, 'venue_type' => $data['venue_type'] ?? $meeting->venue_type, 'venue_id' => $data['venue_id'] ?? $meeting->venue_id]), $meeting);

        return response()->json(['success' => true, 'data' => $conflicts->evaluateMeeting($meeting, $schedule)]);
    }

    private function assertEmployeeScope(MeetingRequest $request, int $chairpersonId, ?array $participantIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', array_merge([$chairpersonId], $participantIds ?? [])))));
        if ($ids === []) return;
        $scopeUnitIds = $request->units()->pluck('organizational_unit_id')->map(fn ($id): int => (int) $id)->all();
        if ($scopeUnitIds === []) $scopeUnitIds = [(int) $request->requesting_unit_id];
        $authorityOfficeUnitId = (int) ($request->authorityContext?->authority_office_unit_id ?? 0);
        if ($authorityOfficeUnitId > 0) $scopeUnitIds[] = $authorityOfficeUnitId;
        $scopeUnitIds = array_values(array_unique($scopeUnitIds));
        $scopeUnits = OrganizationalUnit::query()->whereIn('id', $scopeUnitIds)->get();
        $authorityEmployeeId = (int) ($request->authorityContext?->authority_employee_id ?? 0);
        $employees = Employee::query()->whereIn('id', $ids)->get();
        abort_unless($employees->count() === count($ids), 403, 'EMPLOYEE_OUTSIDE_WORKFLOW_SCOPE');
        abort_unless($employees->every(function (Employee $employee) use ($scopeUnits, $authorityEmployeeId): bool {
            if ((int) $employee->id === $authorityEmployeeId) return true;
            return $scopeUnits->contains(fn (OrganizationalUnit $unit): bool => $unit->isWithinScope((int) $employee->organizational_unit_id));
        }), 403, 'EMPLOYEE_OUTSIDE_WORKFLOW_SCOPE');
    }
}
