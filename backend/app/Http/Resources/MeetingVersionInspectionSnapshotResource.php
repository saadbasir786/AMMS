<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Explicit, read-only snapshots for the version inspector UI. */
class MeetingVersionInspectionSnapshotResource extends JsonResource
{
    public function toArray($request): array
    {
        $domain = $this->resource['domain'];
        $version = $this->resource['version'];
        $data = [
            'id' => $version->id,
            'version_number' => $version->version_number ?? $version->version,
        ];

        return $data + match ($domain) {
            'schedule' => [
                'meeting_date' => $version->meeting_date?->toDateString(),
                'start_time' => $version->start_time,
                'end_time' => $version->end_time,
                'venue_type' => $version->venue_type,
                'location' => $version->location,
                'venue' => $version->venue ? ['name' => $version->venue->name] : null,
                'venue_snapshot' => $version->venue_snapshot,
            ],
            'participants' => [
                'members' => $version->members->map(fn ($member) => [
                    'id' => $member->id,
                    'employee_id' => $member->employee_id,
                    'participant_role' => $member->participant_role,
                    'is_mandatory' => (bool) $member->is_mandatory,
                    'employee' => $member->employee ? [
                        'full_name' => $member->employee->full_name,
                        'first_name' => $member->employee->first_name,
                        'last_name' => $member->employee->last_name,
                    ] : null,
                    'organizational_unit' => $member->organizationalUnit ? ['name' => $member->organizationalUnit->name] : null,
                ])->values()->all(),
            ],
            'minutes' => [
                'summary' => $version->summary,
                'general_notes' => $version->general_notes,
                'items' => $version->items->map(fn ($item) => [
                    'id' => $item->id,
                    'sequence_number' => $item->sequence_number,
                    'agenda_title' => $item->agenda_title,
                    'discussion_summary' => $item->discussion_summary,
                ])->values()->all(),
                'decisions' => $version->decisions->map(fn ($decision) => [
                    'id' => $decision->id,
                    'decision_number' => $decision->decision_number,
                    'decision_text' => $decision->decision_text,
                    'responsible_unit' => $decision->responsibleUnit ? ['name' => $decision->responsibleUnit->name] : null,
                    'responsible_employee' => $decision->responsibleEmployee ? ['full_name' => $decision->responsibleEmployee->full_name] : null,
                    'target_date' => $decision->target_date?->toDateString(),
                    'notes' => $decision->notes,
                ])->values()->all(),
                'attendance_snapshots' => $version->attendanceSnapshots->map(fn ($person) => [
                    'id' => $person->id,
                    'employee_id' => $person->employee_id,
                    'display_name' => $person->display_name,
                    'participant_role' => $person->participant_role,
                    'attendance_status' => $person->attendance_status,
                    'employee' => $person->employee ? ['full_name' => $person->employee->full_name] : null,
                ])->values()->all(),
            ],
            default => [],
        };
    }
}
