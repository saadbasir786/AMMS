<?php

declare(strict_types=1);

namespace App\Actions\Minutes;

use App\Models\Meeting;
use App\Models\MeetingMinutes;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMeetingMinutesDraft
{
    public function execute(User $actor, Meeting $meeting): MeetingMinutes
    {
        try {
            return DB::transaction(function () use ($actor, $meeting): MeetingMinutes {
            $locked = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
            if (!in_array($locked->status, ['IN_PROGRESS', 'HELD'], true)) abort(422, 'MINUTES_NOT_AVAILABLE');
            $existing = MeetingMinutes::query()->where('meeting_id', $locked->id)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->status === MeetingMinutes::FINALIZED) abort(422, 'MINUTES_ALREADY_FINALIZED');
                return $existing->load(['currentVersion', 'meeting']);
            }
            $minutes = MeetingMinutes::create([
                'uuid' => (string) Str::uuid(),
                'meeting_id' => $locked->id,
                'status' => MeetingMinutes::DRAFT,
                'created_by_user_id' => $actor->id,
            ]);
            \App\Models\MeetingAction::create([
                'meeting_id' => $locked->id,
                'actor_id' => $actor->id,
                'action' => 'MINUTES_DRAFT_CREATED',
                'metadata' => ['minutes_id' => $minutes->id],
            ]);
            return $minutes->load(['meeting']);
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'meeting_minutes') || str_contains($e->getMessage(), 'mm_meeting_uq')) {
                return MeetingMinutes::query()->where('meeting_id', $meeting->id)->firstOrFail()->load(['currentVersion', 'meeting']);
            }
            throw $e;
        }
    }
}
