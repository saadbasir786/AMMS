<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Models\Meeting;
use App\Models\MeetingExecutionSession;
use App\Models\MeetingScheduleVersion;
use App\Models\MeetingParticipantVersion;
use App\Models\User;
use App\Support\MeetingExecutionGovernanceResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StartMeetingExecution
{
    public function __construct(private MeetingExecutionGovernanceResolver $governance) {}

    public function execute(User $actor, Meeting $meeting, ?string $startNotes = null): MeetingExecutionSession
    {
        try {
            return DB::transaction(function () use ($actor, $meeting, $startNotes): MeetingExecutionSession {
            $locked = Meeting::query()->with(['request.authorityContext', 'authorityContext', 'currentScheduleVersion', 'currentParticipantVersion'])
                ->lockForUpdate()->findOrFail($meeting->id);

            if (!$this->governance->mayStart($locked, $actor)) {
                abort(response()->json(['success' => false, 'code' => 'UNAUTHORIZED_MEETING_EXECUTION', 'message' => 'اجازهٔ شروع جلسه وجود ندارد.', 'errors' => []], 403));
            }
            if ($locked->request?->status !== 'CONVERTED_TO_MEETING') abort(422, 'MEETING_NOT_EXECUTABLE');
            if ($locked->status === 'IN_PROGRESS') abort(422, 'MEETING_EXECUTION_ALREADY_STARTED');
            if (in_array($locked->status, ['HELD', 'COMPLETED'], true)) abort(422, 'MEETING_EXECUTION_ALREADY_ENDED');
            if ($locked->status !== 'SCHEDULED') abort(422, 'MEETING_NOT_EXECUTABLE');
            if (!$locked->current_schedule_version_id) abort(422, 'MEETING_SCHEDULE_VERSION_REQUIRED');
            if (!$locked->current_participant_version_id) abort(422, 'MEETING_PARTICIPANT_VERSION_REQUIRED');

            MeetingScheduleVersion::query()->lockForUpdate()->findOrFail($locked->current_schedule_version_id);
            MeetingParticipantVersion::query()->lockForUpdate()->findOrFail($locked->current_participant_version_id);

            $existing = MeetingExecutionSession::query()->where('meeting_id', $locked->id)->lockForUpdate()->first();
            if ($existing) {
                abort(422, $existing->status === MeetingExecutionSession::IN_PROGRESS ? 'MEETING_EXECUTION_ALREADY_STARTED' : 'MEETING_EXECUTION_ALREADY_ENDED');
            }

            $now = now();
            $session = MeetingExecutionSession::create([
                'uuid' => (string) Str::uuid(),
                'meeting_id' => $locked->id,
                'schedule_version_id' => $locked->current_schedule_version_id,
                'participant_version_id' => $locked->current_participant_version_id,
                'status' => MeetingExecutionSession::IN_PROGRESS,
                'started_at' => $now,
                'started_by_user_id' => $actor->id,
                'start_notes' => $startNotes,
            ]);
            $locked->forceFill(['status' => 'IN_PROGRESS'])->saveQuietly();
            $this->audit($locked, $actor, 'MEETING_STARTED', 'SCHEDULED', 'IN_PROGRESS', [
                'execution_session_id' => $session->id,
                'schedule_version_id' => $session->schedule_version_id,
                'participant_version_id' => $session->participant_version_id,
            ]);
            return $session->fresh(['meeting', 'scheduleVersion', 'participantVersion']);
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'meeting_execution_sessions') || str_contains($e->getMessage(), 'mes_meeting_uq')) {
                abort(422, 'MEETING_EXECUTION_ALREADY_STARTED');
            }
            throw $e;
        }
    }

    private function audit(Meeting $meeting, User $actor, string $action, ?string $from, ?string $to, array $metadata): void
    {
        \App\Models\MeetingAction::create([
            'meeting_id' => $meeting->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'metadata' => $metadata,
        ]);
    }
}
