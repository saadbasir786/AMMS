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
                'approve_authority' => $this->allows(fn (): bool => $gate->allows('minutesApproveAuthority', $meeting)),
                'finalize' => $this->allows(fn (): bool => $gate->allows('minutesFinalize', $meeting)),
                'document' => $this->allows(fn (): bool => $gate->allows('minutesDocumentView', $meeting)),
            ],
            'terminals' => [
                'view' => $this->allows(fn (): bool => $gate->allows('flow09View', $meeting)),
                'assign' => $this->allows(fn (): bool => $gate->allows('flow09Assign', $meeting)),
                'refresh' => $this->canTerminalOperation($meeting, $actor, 'refresh'),
                'revoke' => $this->canTerminalOperation($meeting, $actor, 'revoke'),
            ],
            'reschedule' => [
                'view' => $this->allows(fn (): bool => $gate->allows('flow07ViewHistory', $meeting)),
                'create' => $this->allows(fn (): bool => $gate->allows('flow07CreateCase', $meeting)),
                'propose' => $this->allows(fn (): bool => $gate->allows('flow07PeerPropose', $meeting)),
                'acknowledge' => $this->allows(fn (): bool => $gate->allows('flow07Acknowledge', $meeting)),
                'request_alternative' => $this->allows(fn (): bool => $gate->allows('flow07RequestAlternative', $meeting)),
                'confirm' => $this->allows(fn (): bool => $gate->allows('flow07Confirm', $meeting)),
                'resolve_alternative' => $this->allows(fn (): bool => $gate->allows('flow07ResolveAlternative', $meeting)),
            ],
            'participant_change' => [
                'view' => $this->allows(fn (): bool => $gate->allows('flow08ViewHistory', $meeting)),
                'create' => $this->allows(fn (): bool => $gate->allows('flow08CreateCase', $meeting)),
                'manage' => $this->canActiveParticipantOperation($meeting, $actor, 'flow08ManageCase'),
                'confirm' => $this->canActiveParticipantOperation($meeting, $actor, 'flow08Confirm'),
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
        $unitIds = $meeting->units()
            ->whereIn('role', ['REQUESTING', 'REQUESTER', 'PARTICIPATING'])
            ->pluck('organizational_unit_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        foreach ($unitIds as $unitId) {
            if ($this->allows(function () use ($actor, $meeting, $unitId): bool {
                return Gate::forUser($actor)->allows('minutesReviewUnit', [$meeting, $unitId]);
            })) {
                return true;
            }
        }

        return false;
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
        if (!$case) return false;

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
            if (!$governance->owner($meeting, $actor)) return false;

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
