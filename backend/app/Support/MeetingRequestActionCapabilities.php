<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Frontend-safe projection of the internal-directorate request workflow.
 * It delegates each decision to the existing policies and permissions.
 */
class MeetingRequestActionCapabilities
{
    /** @return array<string, bool> */
    public function resolve(MeetingRequest $request, User $actor): array
    {
        $gate = Gate::forUser($actor);
        $isInternal = $request->flow_type === 'INTERNAL_DIRECTORATE';

        return [
            'edit' => $isInternal && $this->allows(fn (): bool => $gate->allows('update', $request)),
            'submit' => $isInternal && $this->allows(fn (): bool => $gate->allows('submit', $request)),
            'approve' => $isInternal && $this->allows(fn (): bool => $gate->allows('approveInternal', $request)),
            'approve_with_delegation' => $isInternal
                && app(InternalMeetingApprovalDelegation::class)->scopeFor($actor, $request) !== null,
            'return_for_revision' => $isInternal && $this->mayReview($request, $actor, 'meeting-request.return'),
            'reject' => $isInternal && $this->mayReview($request, $actor, 'meeting-request.reject'),
            'start_scheduling' => $isInternal
                && $request->status === 'APPROVED'
                && $this->allows(fn (): bool => $gate->allows('schedule', $request)),
            'open_scheduling' => $isInternal && (
                ($request->status === 'SCHEDULING' && $this->allows(fn (): bool => $gate->allows('schedule', $request)))
                || ($request->status === 'SCHEDULE_CONFIRMED' && $this->allows(fn (): bool => $gate->allows('finalize', $request)))
            ),
            'finalize' => $isInternal && $this->allows(fn (): bool => $gate->allows('finalize', $request)),
        ];
    }

    private function mayReview(MeetingRequest $request, User $actor, string $permission): bool
    {
        return $actor->can($permission)
            && $this->allows(fn (): bool => Gate::forUser($actor)->allows('review', $request));
    }

    /** Fail closed when a malformed legacy request cannot be evaluated. */
    private function allows(callable $callback): bool
    {
        try {
            return (bool) $callback();
        } catch (Throwable) {
            return false;
        }
    }
}
