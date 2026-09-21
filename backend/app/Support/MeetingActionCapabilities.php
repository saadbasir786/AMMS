<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Meeting;
use App\Models\MeetingExceptionCase;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Projects the authoritative meeting policies into a frontend-safe capability contract.
 * Resolved only for meeting detail/workspace requests, not for paginated resources.
 */
class MeetingActionCapabilities
{
    public function __construct(
        private readonly DecisionFollowUpGovernanceResolver $followUpGovernance,
    ) {}

    public function resolve(Meeting $meeting, User $actor): array
    {
        $gate = Gate::forUser($actor);

        return [
            'execution' => [
                'view' => $this->allows(fn (): bool => $gate->allows('executionView', $meeting)),
                'start' => $this->allows(fn (): bool => $gate->allows('executionStart', $meeting)),
                'end' => $this->allows(fn (): bool => $gate->allows('executionEnd', $meeting)),
            ],
            'minutes' => [
                'view' => $this->allows(fn (): bool => $gate->allows('minutesView', $meeting)),
                'prepare' => $this->allows(fn (): bool => $gate->allows('minutesPrepare', $meeting)),
                'submit' => $this->allows(fn (): bool => $gate->allows('minutesSubmit', $meeting)),
                'review' => $this->canReviewMinutes($meeting, $actor),
                'reviewable_unit_ids' => $this->reviewableMinutesUnitIds($meeting, $actor),
                'delegated_review_unit_ids' => $this->delegatedMinutesReviewUnitIds($meeting, $actor),
                'approve_authority' => $this->allows(fn (): bool => $gate->allows('minutesApproveAuthority', $meeting)),
                'finalize' => $this->allows(fn (): bool => $gate->allows('minutesFinalize', $meeting)),
                'document' => $this->allows(fn (): bool => $gate->allows('minutesDocumentView', $meeting)),
            ],
            'terminals' => [
                'view' => $this->allows(fn (): bool => $gate->allows('terminalAssignmentView', $meeting)),
                'assign' => $this->allows(fn (): bool => $gate->allows('terminalAssignmentAssign', $meeting)),
                'refresh' => $this->canTerminalOperation($meeting, $actor, 'refresh'),
                'revoke' => $this->canTerminalOperation($meeting, $actor, 'revoke'),
            ],
            'reschedule' => [
                'view' => $this->allows(fn (): bool => $gate->allows('meetingRescheduleViewHistory', $meeting)),
                'create' => $this->allows(fn (): bool => $gate->allows('meetingRescheduleCreateCase', $meeting)),
                'propose' => $this->allows(fn (): bool => $gate->allows('meetingReschedulePeerPropose', $meeting)
                    || $gate->allows('meetingRescheduleAuthorityPropose', $meeting)),
                'submit' => $this->allows(fn (): bool => $gate->allows('meetingRescheduleSubmit', $meeting)),
                'acknowledge' => $this->allows(fn (): bool => $gate->allows('meetingRescheduleAcknowledge', $meeting)),
                'return_for_correction' => $this->allows(fn (): bool => $gate->allows('meetingRescheduleReturn', $meeting)),
                'request_alternative' => $this->allows(fn (): bool => $gate->allows('meetingRescheduleRequestAlternative', $meeting)),
                'confirm' => $this->allows(fn (): bool => $gate->allows('meetingRescheduleConfirm', $meeting)),
                'resolve_alternative' => $this->allows(fn (): bool => $gate->allows('meetingRescheduleResolveAlternative', $meeting)),
            ],
            'participant_change' => [
                'view' => $this->allows(fn (): bool => $gate->allows('participantChangeViewHistory', $meeting)),
                'create' => $this->allows(fn (): bool => $gate->allows('participantChangeCreateCase', $meeting)),
                'manage' => $this->canActiveParticipantOperation($meeting, $actor, 'participantChangeManageCase'),
                'confirm' => $this->canActiveParticipantOperation($meeting, $actor, 'participantChangeConfirm'),
            ],
            'exceptions' => [
                'view' => $this->allows(fn (): bool => $gate->allows('viewMeeting', [MeetingExceptionCase::class, $meeting])),
                'create' => $this->allows(fn (): bool => $gate->allows('create', [MeetingExceptionCase::class, $meeting])),
                'submit' => $this->canExceptionOperation($meeting, $actor, 'submit'),
                'withdraw' => $this->canExceptionOperation($meeting, $actor, 'withdraw'),
                'apply' => $this->canExceptionOperation($meeting, $actor, 'apply'),
                'acknowledge' => $this->canExceptionReview($meeting, $actor, 'acknowledge'),
                'approve' => $this->canExceptionReview($meeting, $actor, 'approve'),
            ],
            'follow_up' => [
                'view' => $this->canViewFollowUp($meeting, $actor),
                'initialize' => $this->canInitializeFollowUp($meeting, $actor),
            ],
        ];
    }

    private function canReviewMinutes(Meeting $meeting, User $actor): bool
    {
        return $this->reviewableMinutesUnitIds($meeting, $actor) !== [];
    }

    /** @return array<int, int> */
    private function reviewableMinutesUnitIds(Meeting $meeting, User $actor): array
    {
        $unitIds = $meeting->units()
            ->whereIn('role', ['REQUESTING', 'REQUESTER', 'PARTICIPATING'])
            ->pluck('organizational_unit_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $reviewable = [];
        foreach ($unitIds as $unitId) {
            if (Gate::forUser($actor)->allows('minutesReviewUnit', [$meeting, $unitId])) {
                $reviewable[] = $unitId;
            }
        }

        return $reviewable;
    }

    /** @return array<int, int> */
    private function delegatedMinutesReviewUnitIds(Meeting $meeting, User $actor): array
    {
        return $meeting->units()
            ->whereIn('role', ['REQUESTING', 'REQUESTER', 'PARTICIPATING'])
            ->pluck('organizational_unit_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->filter(fn (int $unitId): bool => app(MinutesGovernanceResolver::class)->delegatedReviewScopeFor($meeting, $actor, $unitId) !== null)
            ->values()
            ->all();
    }

    private function canTerminalOperation(Meeting $meeting, User $actor, string $operation): bool
    {
        return $this->allows(function () use ($meeting, $actor, $operation): bool {
            $resolver = app(TerminalAssignmentGovernanceResolver::class);

            return $operation === 'refresh'
                ? $resolver->mayRefresh($meeting, $actor)
                : $resolver->mayRevoke($meeting, $actor);
        });
    }

    private function canActiveParticipantOperation(Meeting $meeting, User $actor, string $ability): bool
    {
        $case = $meeting->activeParticipantChangeCase()->first();
        if (! $case) {
            return false;
        }

        return $this->allows(fn (): bool => Gate::forUser($actor)->allows($ability, [
            $meeting,
            (string) $case->owner_type,
            $case->owner_unit_id ? (int) $case->owner_unit_id : null,
        ]));
    }

    private function canExceptionOperation(Meeting $meeting, User $actor, string $operation): bool
    {
        return $this->allows(function () use ($meeting, $actor, $operation): bool {
            $governance = app(MeetingExceptionGovernanceResolver::class);
            if (! $governance->owner($meeting, $actor)) {
                return false;
            }

            return $actor->can("meeting.exception.{$operation}");
        });
    }

    private function canExceptionReview(Meeting $meeting, User $actor, string $operation): bool
    {
        return $this->allows(function () use ($meeting, $actor, $operation): bool {
            $reviews = $meeting->exceptionCases()
                ->with('currentVersion.reviews')
                ->get()
                ->flatMap(fn (MeetingExceptionCase $case) => $case->currentVersion?->reviews ?? []);

            return $reviews->contains(fn ($review): bool => Gate::forUser($actor)->allows($operation, $review));
        });
    }

    private function canViewFollowUp(Meeting $meeting, User $actor): bool
    {
        return $meeting->status === 'COMPLETED'
            && $this->hasFinalizedMinutes($meeting)
            && $this->allows(fn (): bool => $this->followUpGovernance->isFollowUpOwner($meeting, $actor));
    }

    private function canInitializeFollowUp(Meeting $meeting, User $actor): bool
    {
        return $meeting->status === 'COMPLETED'
            && $this->hasFinalizedMinutes($meeting)
            && $this->allows(fn (): bool => $this->followUpGovernance->mayInitialize($meeting, $actor));
    }

    private function hasFinalizedMinutes(Meeting $meeting): bool
    {
        return $meeting->minutes()->where('status', 'FINALIZED')->exists();
    }

    /** Fail closed if a malformed legacy meeting cannot be evaluated by a resolver. */
    private function allows(callable $callback): bool
    {
        try {
            return (bool) $callback();
        } catch (Throwable) {
            return false;
        }
    }
}
