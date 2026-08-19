<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Models\Meeting;
use App\Models\MeetingExecutionSession;
use App\Models\User;
use App\Support\MeetingExecutionGovernanceResolver;
use Illuminate\Support\Facades\DB;

class EndMeetingExecution
{
    public function __construct(private MeetingExecutionGovernanceResolver $governance) {}

    public function execute(User $actor, Meeting $meeting, ?string $endNotes = null): MeetingExecutionSession
    {
        return DB::transaction(function () use ($actor, $meeting, $endNotes): MeetingExecutionSession {
            $locked = Meeting::query()->with(['request.authorityContext', 'authorityContext', 'executionSession'])
                ->lockForUpdate()->findOrFail($meeting->id);

            if (!$this->governance->mayEnd($locked, $actor)) {
                abort(response()->json(['success' => false, 'code' => 'UNAUTHORIZED_MEETING_EXECUTION', 'message' => 'اجازهٔ پایان جلسه وجود ندارد.', 'errors' => []], 403));
            }
            if ($locked->status === 'SCHEDULED') abort(422, 'MEETING_EXECUTION_NOT_STARTED');
            if (in_array($locked->status, ['HELD', 'COMPLETED'], true)) abort(422, 'MEETING_EXECUTION_ALREADY_ENDED');
            if ($locked->status !== 'IN_PROGRESS') abort(422, 'MEETING_EXECUTION_NOT_STARTED');

            $session = MeetingExecutionSession::query()->lockForUpdate()->where('meeting_id', $locked->id)->first();
            if (!$session) abort(422, 'MEETING_EXECUTION_NOT_STARTED');
            if ($session->status !== MeetingExecutionSession::IN_PROGRESS) abort(422, 'MEETING_EXECUTION_ALREADY_ENDED');

            $now = now();
            $session->forceFill([
                'status' => MeetingExecutionSession::ENDED,
                'ended_at' => $now,
                'ended_by_user_id' => $actor->id,
                'end_notes' => $endNotes,
            ])->saveQuietly();
            $locked->forceFill(['status' => 'HELD'])->saveQuietly();
            \App\Models\MeetingAction::create([
                'meeting_id' => $locked->id,
                'actor_id' => $actor->id,
                'action' => 'MEETING_ENDED',
                'from_status' => 'IN_PROGRESS',
                'to_status' => 'HELD',
                'metadata' => ['execution_session_id' => $session->id],
            ]);
            return $session->fresh(['meeting', 'scheduleVersion', 'participantVersion']);
        });
    }
}
