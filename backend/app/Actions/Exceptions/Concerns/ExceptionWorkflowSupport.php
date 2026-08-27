<?php

declare(strict_types=1);

namespace App\Actions\Exceptions\Concerns;

use App\Models\AmmsNotification;
use App\Models\Meeting;
use App\Models\MeetingAction;
use App\Models\MeetingExceptionCase;
use App\Models\User;

trait ExceptionWorkflowSupport
{
    protected function audit(Meeting $meeting, User $actor, string $event, ?string $comment = null, array $metadata = []): void
    {
        MeetingAction::create([
            'meeting_id' => $meeting->id,
            'actor_id' => $actor->id,
            'action' => $event,
            'comment' => $comment,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    protected function notify(User|int|null $user, string $type, string $title, string $message, array $data = []): void
    {
        $userId = $user instanceof User ? $user->id : $user;
        if (!$userId) return;
        AmmsNotification::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'message' => $message, 'data' => $data]);
    }

    protected function exceptionMetadata(MeetingExceptionCase $case): array
    {
        return ['exception_case_id' => $case->id, 'exception_case_type' => $case->case_type, 'exception_case_version_id' => $case->current_version_id];
    }
}
