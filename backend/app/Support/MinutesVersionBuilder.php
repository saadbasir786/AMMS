<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MeetingMinuteVersion;
use App\Models\MeetingMinutes;
use App\Models\User;
use Illuminate\Support\Str;

class MinutesVersionBuilder
{
    public function create(MeetingMinutes $minutes, User $actor, array $data, int $versionNumber): MeetingMinuteVersion
    {
        $minutes->loadMissing(['meeting.executionSession', 'meeting.currentScheduleVersion', 'meeting.currentParticipantVersion']);
        $meeting = $minutes->meeting;
        $execution = $meeting->executionSession;
        if (!$execution) abort(422, 'MEETING_EXECUTION_NOT_STARTED');
        if (!in_array($meeting->status, ['IN_PROGRESS', 'HELD'], true)) abort(422, 'MINUTES_NOT_AVAILABLE');
        if (trim((string) ($data['summary'] ?? '')) === '') abort(422, 'MINUTES_SUMMARY_REQUIRED');

        $version = MeetingMinuteVersion::create([
            'uuid' => (string) Str::uuid(),
            'meeting_minutes_id' => $minutes->id,
            'version_number' => $versionNumber,
            'execution_session_id' => $execution->id,
            'schedule_version_id' => $execution->schedule_version_id,
            'participant_version_id' => $execution->participant_version_id,
            'summary' => trim((string) $data['summary']),
            'general_notes' => filled($data['general_notes'] ?? null) ? trim((string) $data['general_notes']) : null,
            'prepared_by_user_id' => $actor->id,
        ]);

        foreach (array_values($data['items'] ?? []) as $index => $item) {
            $title = trim((string) ($item['agenda_title'] ?? ''));
            $discussion = trim((string) ($item['discussion_summary'] ?? ''));
            if ($title === '' || $discussion === '') abort(422, 'MINUTES_ITEM_REQUIRED');
            $version->items()->create([
                'sequence_number' => $index + 1,
                'agenda_title' => $title,
                'discussion_summary' => $discussion,
            ]);
        }

        foreach (array_values($data['decisions'] ?? []) as $index => $decision) {
            $text = trim((string) ($decision['decision_text'] ?? ''));
            if ($text === '') abort(422, 'MINUTES_DECISION_REQUIRED');
            $responsibleUnitId = $decision['responsible_unit_id'] ?? null;
            $responsibleEmployeeId = $decision['responsible_employee_id'] ?? null;
            if ($responsibleUnitId && !\App\Models\OrganizationalUnit::query()->where('id', $responsibleUnitId)->where('organization_id', $meeting->organizingUnit?->organization_id ?? $meeting->units()->with('unit')->first()?->unit?->organization_id)->exists()) {
                abort(422, 'MINUTES_DECISION_UNIT_INVALID');
            }
            if ($responsibleEmployeeId && !\App\Models\Employee::query()->whereKey($responsibleEmployeeId)->where('status', 'ACTIVE')->exists()) abort(422, 'MINUTES_DECISION_EMPLOYEE_INVALID');
            $version->decisions()->create([
                'uuid' => (string) Str::uuid(),
                'sequence_number' => $index + 1,
                'decision_number' => 'DEC-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'decision_text' => $text,
                'responsible_unit_id' => $responsibleUnitId,
                'responsible_employee_id' => $responsibleEmployeeId,
                'target_date' => $decision['target_date'] ?? null,
                'notes' => $decision['notes'] ?? null,
            ]);
        }

        app(MeetingAttendanceSnapshotBuilder::class)->rebuild($version);
        return $version->fresh(['items', 'decisions', 'attendanceSnapshots', 'reviews']);
    }
}
