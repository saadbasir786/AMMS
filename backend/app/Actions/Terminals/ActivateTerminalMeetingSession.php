<?php

namespace App\Actions\Terminals;

use App\Models\Meeting;
use App\Models\MeetingTerminalAssignment;
use App\Models\Terminal;
use App\Models\TerminalMeetingSession;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivateTerminalMeetingSession
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function execute(Terminal $terminal, MeetingTerminalAssignment $assignment): array
    {
        return DB::transaction(function () use ($terminal, $assignment): array {
            // FLOW-09 lock order: Terminal -> Meeting -> Assignment/Session.
            $lockedTerminal = Terminal::query()->lockForUpdate()->findOrFail($terminal->id);
            $meeting = Meeting::query()
                ->with(['currentScheduleVersion', 'currentParticipantVersion'])
                ->lockForUpdate()
                ->findOrFail($assignment->meeting_id);
            $lockedAssignment = MeetingTerminalAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ((int) $lockedAssignment->terminal_id !== (int) $lockedTerminal->id) abort(404, 'TERMINAL_ASSIGNMENT_NOT_FOUND');
            if ($lockedTerminal->status !== 'ACTIVE') abort(422, 'TERMINAL_NOT_AVAILABLE');
            if ($meeting->status !== 'SCHEDULED') abort(422, 'MEETING_NOT_SCHEDULED');
            if ($lockedAssignment->status === 'REVOKED') abort(422, 'TERMINAL_ASSIGNMENT_REVOKED');
            if ($lockedAssignment->status === 'EXPIRED' || !$lockedAssignment->isWindowOpen()) abort(422, 'TERMINAL_ASSIGNMENT_EXPIRED');

            $stale = $lockedAssignment->staleness($meeting);
            if ($stale['schedule_changed'] || $stale['participants_changed']) {
                $lockedAssignment->forceFill(['status' => 'STALE'])->saveQuietly();
                $this->staleResponse($stale);
            }

            $activeSessions = TerminalMeetingSession::query()
                ->where('terminal_id', $lockedTerminal->id)
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->get();
            foreach ($activeSessions as $active) {
                if ($active->isExpired()) {
                    $active->forceFill(['status' => 'EXPIRED'])->saveQuietly();
                    continue;
                }
                abort(422, 'TERMINAL_SESSION_ALREADY_ACTIVE');
            }

            $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $session = TerminalMeetingSession::create([
                'uuid' => (string) Str::uuid(),
                'assignment_id' => $lockedAssignment->id,
                'terminal_id' => $lockedTerminal->id,
                'meeting_id' => $meeting->id,
                'schedule_version_id' => $lockedAssignment->baseline_schedule_version_id,
                'participant_version_id' => $lockedAssignment->baseline_participant_version_id,
                'status' => 'ACTIVE',
                'session_token_hash' => hash('sha256', $raw),
                'activated_at' => now(),
                'expires_at' => $lockedAssignment->valid_until,
            ]);
            $lockedTerminal->forceFill(['current_session_id' => $session->id])->save();
            $meeting->forceFill(['current_terminal_session_id' => $session->id])->save();

            $this->audit->record('TERMINAL_SESSION_ACTIVATED', null, $lockedTerminal, $meeting, $lockedAssignment, $session, [
                'schedule_version_id' => $session->schedule_version_id,
                'participant_version_id' => $session->participant_version_id,
                'expires_at' => $session->expires_at->toIso8601String(),
            ]);

            return [
                'session' => $session->fresh(['terminal', 'assignment', 'meeting']),
                'session_token' => $raw,
            ];
        });
    }

    private function staleResponse(array $stale): never
    {
        abort(response()->json([
            'success' => false,
            'code' => 'TERMINAL_ASSIGNMENT_STALE',
            'message' => 'نسخهٔ تخصیص ترمینال با نسخهٔ فعلی جلسه هم‌خوان نیست.',
            'errors' => $stale,
        ], 422));
    }
}
