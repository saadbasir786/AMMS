<?php

namespace App\Actions\Meetings;

use App\Models\Meeting;
use App\Models\MeetingTerminalAssignment;
use App\Models\Terminal;
use App\Models\User;
use App\Support\TerminalAssignmentConflictGuard;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignTerminalToMeeting
{
    public function __construct(
        private TerminalAssignmentConflictGuard $guard,
        private TerminalAuditLogger $audit,
    ) {}

    public function execute(User $actor, Meeting $meeting, Terminal $terminal, string $validFrom, string $validUntil): MeetingTerminalAssignment
    {
        return DB::transaction(function () use ($actor, $meeting, $terminal, $validFrom, $validUntil): MeetingTerminalAssignment {
            // FLOW-09 lock order: Terminal -> Meeting -> Assignment/Session.
            // Keep this consistent with revoke/refresh/activate to avoid cross-action deadlocks.
            $lockedTerminal = Terminal::query()->lockForUpdate()->findOrFail($terminal->id);
            $lockedMeeting = Meeting::query()
                ->with(['request', 'authorityContext', 'currentScheduleVersion', 'currentParticipantVersion'])
                ->lockForUpdate()
                ->findOrFail($meeting->id);
            [$from, $until] = $this->guard->parseWindow($validFrom, $validUntil);
            $this->guard->assertAssignable($lockedTerminal, $lockedMeeting, $from, $until);

            $assignment = MeetingTerminalAssignment::create([
                'uuid' => (string) Str::uuid(),
                'meeting_id' => $lockedMeeting->id,
                'terminal_id' => $lockedTerminal->id,
                'baseline_schedule_version_id' => $lockedMeeting->current_schedule_version_id,
                'baseline_participant_version_id' => $lockedMeeting->current_participant_version_id,
                'valid_from' => $from,
                'valid_until' => $until,
                'status' => 'ASSIGNED',
                'assigned_by_user_id' => $actor->id,
                'assigned_at' => now(),
            ]);

            $this->audit->record('TERMINAL_ASSIGNED_TO_MEETING', $actor, $lockedTerminal, $lockedMeeting, $assignment, metadata: [
                'terminal_uuid' => $lockedTerminal->uuid,
                'meeting_id' => $lockedMeeting->id,
                'schedule_version_id' => $assignment->baseline_schedule_version_id,
                'participant_version_id' => $assignment->baseline_participant_version_id,
                'valid_from' => $assignment->valid_from->toIso8601String(),
                'valid_until' => $assignment->valid_until->toIso8601String(),
                'assigned_by' => $actor->id,
            ]);

            return $assignment->fresh(['terminal', 'meeting', 'baselineScheduleVersion', 'baselineParticipantVersion']);
        });
    }
}
