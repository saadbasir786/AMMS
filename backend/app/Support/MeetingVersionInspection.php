<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Meeting;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionCaseVersion;
use App\Models\MeetingMinuteVersion;
use App\Models\MeetingParticipantVersion;
use App\Models\MeetingScheduleVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Read-only, domain-aware access and comparisons for immutable meeting snapshots. */
class MeetingVersionInspection
{
    public const MEETING_DOMAINS = ['schedule', 'participants', 'minutes', 'exception'];

    public function query(Meeting $meeting, string $domain): Builder
    {
        return match ($domain) {
            'schedule' => $meeting->scheduleVersions()->getQuery(),
            'participants' => $meeting->participantVersions()->getQuery(),
            'minutes' => $meeting->minutes()->firstOrFail()->versions()->getQuery(),
            'exception' => abort(404),
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

    public function exceptionQuery(MeetingExceptionCase $case): Builder
    {
        return $case->versions()->getQuery();
    }

    public function loadException(MeetingExceptionCase $case, int $id): MeetingExceptionCaseVersion
    {
        $version = $case->versions()->whereKey($id)->with(['preparedBy', 'baselineScheduleVersion', 'baselineParticipantVersion.members'])->firstOrFail();
        if ($version->baselineScheduleVersion && (int) $version->baselineScheduleVersion->meeting_id !== (int) $case->meeting_id) $version->setRelation('baselineScheduleVersion', null);
        if ($version->baselineParticipantVersion && (int) $version->baselineParticipantVersion->meeting_id !== (int) $case->meeting_id) $version->setRelation('baselineParticipantVersion', null);
        return $version;
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

    public function isCurrentException(MeetingExceptionCase $case, MeetingExceptionCaseVersion $version): bool
    {
        return (int) $case->current_version_id === (int) $version->id;
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
            'exception' => $this->compareFields($from, $to, [
                'reason' => 'دلیل', 'administrative_note' => 'یادداشت اداری', 'replacement_expected' => 'انتظار جایگزین',
                'proposed_resume_date' => 'تاریخ پیشنهادی ازسرگیری', 'baseline_meeting_status' => 'وضعیت مبنا',
                'baseline_schedule_version_id' => 'نسخه زمان‌بندی مبنا', 'baseline_participant_version_id' => 'نسخه اشتراک‌کنندگان مبنا',
            ]),
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

    /** Return immutable participant operations in the selected from-to direction. */
    private function participantChangeHistory(MeetingParticipantVersion $from, MeetingParticipantVersion $to): array
    {
        $fromNumber = (int) $from->version_number;
        $toNumber = (int) $to->version_number;
        if ($fromNumber === $toNumber) return [];
        $forward = $fromNumber < $toNumber;

        $versions = MeetingParticipantVersion::query()
            ->where('meeting_id', $from->meeting_id)
            ->where('version_number', $forward ? '>' : '<=', $fromNumber)
            ->where('version_number', $forward ? '<=' : '>=', $toNumber)
            ->with(['sourceChangeCase.items.oldEmployee.unit', 'sourceChangeCase.items.newEmployee.unit'])
            ->orderBy('version_number', $forward ? 'asc' : 'desc')
            ->get();

        $person = static fn ($employee): ?array => $employee ? [
            'name' => $employee->full_name ?? trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'unit' => $employee->unit?->name,
        ] : null;

        return $versions->flatMap(function (MeetingParticipantVersion $version) use ($person, $forward): array {
            if (! $version->sourceChangeCase) return [];

            return $version->sourceChangeCase->items->map(function ($item) use ($person, $forward, $version): array {
                $operation = strtolower((string) $item->operation);
                if ($forward) {
                    return [
                        'version_number' => (int) $version->version_number,
                        'type' => $operation,
                        'old' => $person($item->oldEmployee),
                        'new' => $person($item->newEmployee),
                        'role' => $item->participant_role,
                    ];
                }

                return [
                    'version_number' => (int) $version->version_number,
                    'type' => match ($operation) {
                        'add' => 'remove',
                        'remove' => 'add',
                        default => $operation,
                    },
                    'old' => $person($item->newEmployee),
                    'new' => $person($item->oldEmployee),
                    'role' => $item->participant_role,
                ];
            })->all();
        })->values()->all();
    }

    private function compareMinutes(MeetingMinuteVersion $from, MeetingMinuteVersion $to): array
    {
        $collections = [];
        foreach ([
            'items' => ['key' => fn ($v) => (string) $v->sequence_number, 'label' => 'بند دستورکار', 'fields' => ['sequence_number', 'agenda_title', 'discussion_summary']],
            'decisions' => ['key' => fn ($v) => $this->businessKey((string) $v->decision_number), 'label' => 'تصمیم', 'fields' => ['decision_number', 'decision_text', 'responsible_unit_id', 'responsible_employee_id', 'target_date', 'notes']],
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
            'context' => ['schedule' => ['from' => $this->scheduleContext($from->relationLoaded('scheduleVersion') ? $from->getRelation('scheduleVersion') : null, $from->schedule_version_id), 'to' => $this->scheduleContext($to->relationLoaded('scheduleVersion') ? $to->getRelation('scheduleVersion') : null, $to->schedule_version_id)],
                'participants' => ['from' => $this->participantContext($from->relationLoaded('participantVersion') ? $from->getRelation('participantVersion') : null, $from->participant_version_id), 'to' => $this->participantContext($to->relationLoaded('participantVersion') ? $to->getRelation('participantVersion') : null, $to->participant_version_id)]]];
    }

    private function scheduleContext(?object $version, mixed $fallbackId = null): ?array
    {
        if (! $version) return $fallbackId ? ['id' => $fallbackId, 'version_number' => null] : null;
        return ['id' => $version->id, 'version_number' => $version->version_number, 'meeting_date' => $version->meeting_date?->toDateString(), 'start_time' => $version->start_time, 'end_time' => $version->end_time, 'location' => $version->location];
    }

    private function participantContext(?object $version, mixed $fallbackId = null): ?array
    {
        if (! $version) return $fallbackId ? ['id' => $fallbackId, 'version_number' => null, 'members_count' => null] : null;
        return ['id' => $version->id, 'version_number' => $version->version_number, 'members_count' => $version->relationLoaded('members') ? $version->members->count() : null];
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
