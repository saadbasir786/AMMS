<?php

namespace App\Policies;

use App\Models\MeetingRequestAttachment;
use App\Models\User;

class MeetingRequestAttachmentPolicy
{
    public function before(User $user): ?bool
    {
        if (!$user->isActive()) return false;
        return null;
    }

    public function view(User $user, MeetingRequestAttachment $attachment): bool
    {
        return $user->isActive() && $user->can('meeting-request.attachment-download') && $attachment->request && $user->can('view', $attachment->request);
    }

    public function create(User $user, MeetingRequestAttachment $attachment): bool
    {
        return $user->isActive() && $user->can('meeting-request.attach') && $attachment->request && $user->can('update', $attachment->request);
    }

    public function delete(User $user, MeetingRequestAttachment $attachment): bool
    {
        return $user->isActive() && $user->can('meeting-request.attachment-delete') && $attachment->request && $user->can('update', $attachment->request);
    }
}
