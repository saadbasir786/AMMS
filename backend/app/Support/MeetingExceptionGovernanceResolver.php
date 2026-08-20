<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Meeting;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionCaseVersion;
use App\Models\MeetingExceptionReview;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class MeetingExceptionGovernanceResolver
{
    public function resolve(Meeting $meeting): array
    {
        $meeting->loadMissing(['request', 'units', 'authorityContext']);
        $schedule = app(ScheduleGovernanceResolver::class)->resolve($meeting);
        $flowType = (string) ($meeting->request?->flow_type ?? 'INTERNAL_DIRECTORATE');
        $owner = $schedule['authority_controlled']
            ? ['role' => 'EXECUTIVE_SECRETARY', 'unit_id' => (int) $schedule['authority_office_unit_id']]
            : ['role' => 'EXECUTIVE_MANAGEMENT', 'unit_id' => (int) $meeting->organizing_unit_id];

        return [
            'flow_type' => $flowType,
            'mode' => $schedule['mode'],
            'owner_role' => $owner['role'],
            'owner_unit_id' => $owner['unit_id'],
            'authority_controlled' => $schedule['authority_controlled'],
            'authority_employee_id' => (int) $schedule['authority_employee_id'],
            'formal_units' => $schedule['formal_units'],
            'required_units' => $this->requiredUnits($meeting, $schedule),
            'required_unit_ids' => $this->requiredUnits($meeting, $schedule)->pluck('organizational_unit_id')->map(fn ($id): int => (int) $id)->values()->all(),
        ];
    }

    public function requiredUnits(Meeting $meeting, ?array $resolved = null): Collection
    {
        $resolved ??= app(ScheduleGovernanceResolver::class)->resolve($meeting);
        $flowType = $resolved['flow_type'];
        $formal = collect($resolved['formal_units']);
        return $flowType === 'HIGH_AUTHORITY_INITIATED'
            ? $formal->where('role', 'PARTICIPATING')->values()
            : $formal;
    }

    public function owner(Meeting $meeting, User $user): bool
    {
        if (!$user->isActive()) return false;
        $governance = $this->resolve($meeting);
        return $user->hasScopedRole($governance['owner_role'], $governance['owner_unit_id']);
    }

    public function mayView(Meeting $meeting, User $user): bool
    {
        if (!$user->isActive() || !$user->can('meeting.exception.view')) return false;
        if (app(\App\Policies\MeetingPolicy::class)->view($user, $meeting)) return true;
        $governance = $this->resolve($meeting);
        $unitId = (int) ($user->employee?->organizational_unit_id ?? 0);
        if ($user->hasScopedRole('DIRECTOR', $unitId) && in_array($unitId, $governance['required_unit_ids'], true)) return true;
        return $governance['authority_controlled'] && (int) ($user->employee_id ?? 0) === (int) $governance['authority_employee_id'];
    }

    public function mayHistoryView(Meeting $meeting, User $user): bool
    {
        return $this->mayView($meeting, $user) && $user->can('meeting.exception.history.view');
    }

    public function mayPrepare(Meeting $meeting, User $user): bool
    {
        return $this->mayView($meeting, $user) && $user->can('meeting.exception.create') && $this->owner($meeting, $user);
    }

    public function mayUpdateDraft(MeetingExceptionCase $case, User $user): bool
    {
        return $case->meeting && $this->mayView($case->meeting, $user) && $user->can('meeting.exception.update-draft') && $this->owner($case->meeting, $user);
    }

    public function maySubmit(MeetingExceptionCase $case, User $user): bool
    {
        return $case->meeting && $this->mayView($case->meeting, $user) && $user->can('meeting.exception.submit') && $this->owner($case->meeting, $user);
    }

    public function mayWithdraw(MeetingExceptionCase $case, User $user): bool
    {
        return $case->meeting && $this->mayView($case->meeting, $user) && $user->can('meeting.exception.withdraw') && $this->owner($case->meeting, $user);
    }

    public function mayApply(MeetingExceptionCase $case, User $user): bool
    {
        return $case->meeting && $this->mayView($case->meeting, $user) && $user->can('meeting.exception.apply') && $this->owner($case->meeting, $user);
    }

    public function mayAcknowledge(MeetingExceptionReview $review, User $user): bool
    {
        $meeting = $review->version?->exceptionCase?->meeting;
        if (!$meeting || !$this->mayView($meeting, $user) || !$user->can('meeting.exception.acknowledge')) return false;
        $governance = $this->resolve($meeting);
        return $review->reviewer_type === MeetingExceptionReview::UNIT
            && $review->review_requirement === MeetingExceptionReview::ACKNOWLEDGEMENT
            && in_array((int) $review->organizational_unit_id, $governance['required_unit_ids'], true)
            && (int) ($user->employee?->organizational_unit_id ?? 0) === (int) $review->organizational_unit_id;
    }

    public function mayApprove(MeetingExceptionReview $review, User $user): bool
    {
        $meeting = $review->version?->exceptionCase?->meeting;
        if (!$meeting || !$this->mayView($meeting, $user) || !$user->can('meeting.exception.approve')) return false;
        $governance = $this->resolve($meeting);
        if ($review->reviewer_type === MeetingExceptionReview::HIGH_AUTHORITY) {
            return $review->review_requirement === MeetingExceptionReview::APPROVAL
                && (int) $review->authority_employee_id === (int) $governance['authority_employee_id']
                && (int) ($user->employee_id ?? 0) === (int) $governance['authority_employee_id'];
        }
        return $this->mayApproveUnit($review, $user);
    }

    public function mayApproveUnit(MeetingExceptionReview $review, User $user): bool
    {
        $meeting = $review->version?->exceptionCase?->meeting;
        if (!$meeting || $review->reviewer_type !== MeetingExceptionReview::UNIT || $review->review_requirement !== MeetingExceptionReview::APPROVAL || !$this->mayView($meeting, $user) || !$user->can('meeting.exception.review')) return false;
        $governance = $this->resolve($meeting);
        return in_array((int) $review->organizational_unit_id, $governance['required_unit_ids'], true)
            && $user->hasScopedRole('DIRECTOR', (int) $review->organizational_unit_id);
    }

    public function reviewRequirements(Meeting $meeting, string $caseType): array
    {
        $governance = $this->resolve($meeting);
        $rows = [];
        foreach ($governance['required_units'] as $unit) {
            $rows[] = ['reviewer_type' => MeetingExceptionReview::UNIT, 'organizational_unit_id' => (int) $unit->organizational_unit_id, 'authority_employee_id' => null, 'review_requirement' => $this->unitRequirement($governance['flow_type'], $caseType)];
        }
        if ($governance['authority_controlled']) {
            $rows[] = ['reviewer_type' => MeetingExceptionReview::HIGH_AUTHORITY, 'organizational_unit_id' => null, 'authority_employee_id' => $governance['authority_employee_id'], 'review_requirement' => MeetingExceptionReview::APPROVAL];
        }
        return $rows;
    }

    private function unitRequirement(string $flowType, string $caseType): string
    {
        return in_array($flowType, ['DIRECTORATE_WITH_HIGH_AUTHORITY', 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY', 'HIGH_AUTHORITY_INITIATED'], true)
            ? MeetingExceptionReview::ACKNOWLEDGEMENT
            : ($flowType === 'INTERNAL_DIRECTORATE' ? MeetingExceptionReview::APPROVAL : MeetingExceptionReview::APPROVAL);
    }
}
