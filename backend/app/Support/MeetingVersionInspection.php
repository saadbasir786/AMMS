<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Meeting;
use App\Models\MeetingMinuteVersion;
use App\Models\MeetingParticipantVersion;
use App\Models\MeetingScheduleVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Read-only, domain-aware access and comparisons for immutable meeting snapshots. */
class MeetingVersionInspection
{
    public const MEETING_DOMAINS = ['schedule', 'participants', 'minutes'];

    public function query(Meeting $meeting, string $domain): Builder
    {
        return match ($domain) {
            'schedule' => $meeting->scheduleVersions()->getQuery(),
            'participants' => $meeting->participantVersions()->getQuery(),
            'minutes' => $meeting->minutes()->firstOrFail()->versions()->getQuery(),
            default => abort(404),
        };
    }

    public function load(Meeting $meeting, string $domain, int $id): mixed
    {
        $version = $this->query($meeting, $domain)->whereKey($id)->firstOrFail();
        return match ($domain) {
            'schedule' => $this->loadSchedule($meeting, $version),
            'participants' => $this->loadParticipants($meeting, $version),
            'minutes' => $this->loadMinutes($meeting, $version),
            default => abort(404),
        };
    }

    public function isCurrent(Meeting $meeting, string $domain, object $version): bool
    {
        return match ($domain) {
            'schedule' => (int) $meeting->current_schedule_version_id === (int) $version->id,
            'participants' => (int) $meeting->current_participant_version_id === (int) $version->id,
            'minutes' => (int) $meeting->getAttribute('minutes_current_version_id') === (int) $version->id,
            default => false,
        };
    }

    public function compare(string $domain, object $from, object $to): array
    {
        return match ($domain) {
            'schedule' => $this->compareFields($from, $to, [
                'meeting_date' => 'تاریخ جلسه', 'start_time' => 'زمان آغاز', 'end_time' => 'زمان پایان',
                'venue_type' => 'نوع محل', 'venue_id' => 'محل مدیریت‌شده', 'location' => 'نشانی / محل', 'venue_snapshot' => 'جزئیات تاریخی محل',
            ]),
            'participants' => $this->compareParticipants($from, $to),
            'minutes' => $this->compareMinutes($from, $to),
            default => abort(404),
        };
    }

    private function compareFields(object $from, object $to, array $labels): array
    {
        $changes = [];
        foreach ($labels as $field => $label) {
            $a = $this->value($from->{$field} ?? null);
            $b = $this->value($to->{$field} ?? null);
            $changes[] = ['key' => $field, 'label' => $label, 'from' => $a, 'to' => $b, 'changed' => $a !== $b];
        }
        return ['fields' => $changes];
    }

    private function compareParticipants(MeetingParticipantVersion $from, MeetingParticipantVersion $to): array
    {
        $key = fn ($member) => (string) $member->employee_id;
        $left = $from->members->keyBy($key);
        $right = $to->members->keyBy($key);
        $added = $right->diffKeys($left)->map(fn ($member) => $this->memberSummary($member))->values();
        $removed = $left->diffKeys($right)->map(fn ($member) => $this->memberSummary($member))->values();
        $changed = [];
        $unchanged = [];
        foreach ($left->intersectByKeys($right) as $id => $old) {
            $new = $right->get($id);
            $before = ['role' => $old->participant_role, 'unit_id' => $old->organizational_unit_id, 'unit' => $old->organizationalUnit?->name];
            $after = ['role' => $new->participant_role, 'unit_id' => $new->organizational_unit_id, 'unit' => $new->organizationalUnit?->name];
            $entry = ['employee_id' => $old->employee_id, 'name' => $old->employee?->full_name ?? trim(($old->employee?->first_name ?? '').' '.($old->employee?->last_name ?? '')), 'from' => $before, 'to' => $after];
            if ($before === $after) $unchanged[] = $entry; else $changed[] = $entry;
        }
        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'unchanged' => $unchanged,
            'history' => $this->participantChangeHistory($from, $to),
        ];
    }

    /** Return the immutable participant operations between two snapshots in chronological order. */
    private function participantChangeHistory(MeetingParticipantVersion $from, MeetingParticipantVersion $to): array
    {
        $firstVersion = min((int) $from->version_number, (int) $to->version_number);
        $lastVersion = max((int) $from->version_number, (int) $to->version_number);
        if ($lastVersion <= $firstVersion) return [];

        $versions = MeetingParticipantVersion::query()
            ->where('meeting_id', $from->meeting_id)
            ->where('version_number', '>', $firstVersion)
            ->where('version_number', '<=', $lastVersion)
            ->with(['sourceChangeCase.items.oldEmployee.unit', 'sourceChangeCase.items.newEmployee.unit'])
            ->orderBy('version_number')
            ->get();

        $person = static fn ($employee): ?array => $employee ? [
            'name' => $employee->full_name ?? trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'unit' => $employee->unit?->name,
        ] : null;

        return $versions->flatMap(function (MeetingParticipantVersion $version) use ($person): array {
            if (! $version->sourceChangeCase) return [];

            return $version->sourceChangeCase->items->map(fn ($item): array => [
                'version_number' => (int) $version->version_number,
                'type' => strtolower((string) $item->operation),
                'old' => $person($item->oldEmployee),
                'new' => $person($item->newEmployee),
                'role' => $item->participant_role,
            ])->all();
        })->values()->all();
    }

    private function compareMinutes(MeetingMinuteVersion $from, MeetingMinuteVersion $to): array
    {
        $collections = [];
        foreach ([
            'items' => ['key' => fn ($v) => $this->businessKey((string) $v->agenda_title), 'label' => 'بند دستورکار', 'fields' => ['sequence_number', 'agenda_title', 'discussion_summary']],
            'decisions' => ['key' => fn ($v) => $this->businessKey((string) $v->decision_text), 'label' => 'تصمیم', 'fields' => ['decision_number', 'decision_text', 'responsible_unit_id', 'responsible_employee_id', 'target_date', 'notes']],
            'attendanceSnapshots' => ['key' => fn ($v) => (string) $v->employee_id, 'label' => 'حاضری', 'fields' => ['employee_id', 'organizational_unit_id', 'participant_role', 'display_name', 'attendance_status']],
        ] as $relation => $config) {
            $a = $from->{$relation}->keyBy($config['key']);
            $b = $to->{$relation}->keyBy($config['key']);
            $collections[$relation] = [
                'added' => $b->diffKeys($a)->map(fn ($item) => $this->businessValues($item, $config['fields']))->values(),
                'removed' => $a->diffKeys($b)->map(fn ($item) => $this->businessValues($item, $config['fields']))->values(),
                'changed' => $a->intersectByKeys($b)
                    ->filter(fn ($old, $id) => $this->businessValues($old, $config['fields']) !== $this->businessValues($b->get($id), $config['fields']))
                    ->map(fn ($old, $id) => ['from' => $this->businessValues($old, $config['fields']), 'to' => $this->businessValues($b->get($id), $config['fields'])])->values(),
                'unchanged' => $a->intersectByKeys($b)
                    ->filter(fn ($old, $id) => $this->businessValues($old, $config['fields']) === $this->businessValues($b->get($id), $config['fields']))
                    ->map(fn ($item) => $this->businessValues($item, $config['fields']))->values(),
                'label' => $config['label'],
            ];
        }
        return ['fields' => $this->compareFields($from, $to, ['summary' => 'خلاصه', 'general_notes' => 'یادداشت‌های عمومی'])['fields'], 'collections' => $collections,
            'context' => ['schedule' => ['from' => $from->schedule_version_id, 'to' => $to->schedule_version_id], 'participants' => ['from' => $from->participant_version_id, 'to' => $to->participant_version_id]]];
    }

    private function memberSummary(object $member): array
    {
        return ['employee_id' => $member->employee_id, 'name' => $member->employee?->full_name ?? trim(($member->employee?->first_name ?? '').' '.($member->employee?->last_name ?? '')), 'role' => $member->participant_role, 'unit_id' => $member->organizational_unit_id];
    }

    private function loadSchedule(Meeting $meeting, MeetingScheduleVersion $version): MeetingScheduleVersion
    {
        $version->load(['venue', 'sourceRescheduleCase', 'createdBy']);
        if ($version->sourceRescheduleCase && (int) $version->sourceRescheduleCase->meeting_id !== (int) $meeting->id) $version->setRelation('sourceRescheduleCase', null);
        return $version;
    }

    private function loadParticipants(Meeting $meeting, MeetingParticipantVersion $version): MeetingParticipantVersion
    {
        $version->load(['members.employee.unit', 'members.organizationalUnit', 'sourceChangeCase.items.oldEmployee.unit', 'sourceChangeCase.items.newEmployee.unit', 'createdBy']);
        if ($version->sourceChangeCase && (int) $version->sourceChangeCase->meeting_id !== (int) $meeting->id) $version->setRelation('sourceChangeCase', null);
        return $version;
    }

    private function loadMinutes(Meeting $meeting, MeetingMinuteVersion $version): MeetingMinuteVersion
    {
        $version->load(['items', 'decisions.responsibleUnit', 'decisions.responsibleEmployee', 'attendanceSnapshots.employee', 'reviews.organizationalUnit', 'scheduleVersion', 'participantVersion.members.employee.unit', 'preparedBy']);
        if ($version->scheduleVersion && (int) $version->scheduleVersion->meeting_id !== (int) $meeting->id) $version->setRelation('scheduleVersion', null);
        if ($version->participantVersion && (int) $version->participantVersion->meeting_id !== (int) $meeting->id) $version->setRelation('participantVersion', null);
        return $version;
    }

    private function businessValues(object $model, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) $values[$field] = $this->value($model->{$field});
        return $values;
    }

    private function businessKey(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    private function value(mixed $value): mixed
    {
        if ($value instanceof \Illuminate\Contracts\Support\Arrayable) return $this->value($value->toArray());
        if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d H:i:s');
        if (is_array($value)) { ksort($value); foreach ($value as &$part) $part = $this->value($part); }
        return $value;
    }
}
