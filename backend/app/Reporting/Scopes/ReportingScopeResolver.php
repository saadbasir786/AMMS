<?php

declare(strict_types=1);

namespace App\Reporting\Scopes;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ReportingScopeResolver
{
    public function __construct(private readonly User $user) {}

    public function user(): User { return $this->user; }

    public function requirePermission(string $permission): void
    {
        abort_unless($this->user->isActive(), 401, 'UNAUTHORIZED');
        abort_unless($this->user->can($permission), 403, 'REPORTING_PERMISSION_DENIED');
    }

    public function unitIds(): array
    {
        return array_values(array_unique(array_merge(
            $this->user->scopedRoleUnitIds('DIRECTOR'),
            $this->user->scopedRoleUnitIds('MEETING_COORDINATOR'),
            $this->user->scopedRoleUnitIds('EXECUTIVE_MANAGEMENT'),
            $this->user->scopedRoleUnitIds('AUTHORITY_OFFICE'),
            $this->user->scopedRoleUnitIds('EXECUTIVE_SECRETARY'),
        )));
    }

    public function allowsOrganizationBusiness(): bool
    {
        return $this->user->can('reporting.dashboard.executive')
            && ($this->user->hasRole('EXECUTIVE_MANAGEMENT', 'sanctum') || $this->user->hasScopedRole('HIGH_AUTHORITY'));
    }

    public function allowsTechnical(): bool
    {
        return $this->user->can('reporting.dashboard.technical') && $this->user->can('reporting.technical.view');
    }

    public function assertUnitFilter(?int $unitId): void
    {
        if ($unitId === null || $this->allowsOrganizationBusiness()) return;
        if (!in_array($unitId, $this->unitIds(), true)) {
            throw new HttpException(403, 'REPORTING_SCOPE_DENIED');
        }
    }

    /**
     * Apply the caller’s business Meeting visibility without loading rows into PHP.
     */
    public function constrainMeetings(Builder $query, ?int $requestedUnitId = null, string $attribution = 'INVOLVED_UNIT'): Builder
    {
        $this->assertUnitFilter($requestedUnitId);
        if ($requestedUnitId !== null) {
            return $this->applyUnitAttribution($query, $requestedUnitId, $attribution);
        }
        if ($this->allowsOrganizationBusiness()) return $query;

        $unitIds = $this->unitIds();
        $userId = $this->user->id;
        $role = $this->user->employee?->organizational_unit_id;

        $query->where(function (Builder $q) use ($unitIds, $userId, $role): void {
            if ($unitIds) {
                $q->whereIn('meetings.organizing_unit_id', $unitIds)
                    ->orWhereHas('units', fn (Builder $units) => $units->whereIn('organizational_unit_id', $unitIds));
            }
            $q->orWhere('meetings.coordinator_id', $userId)
                ->orWhereHas('participants', fn (Builder $participants) => $participants->where('employee_id', $this->user->employee_id));
            if ($role) $q->orWhereHas('authorityContext', fn (Builder $authority) => $authority->where('authority_employee_id', $this->user->employee_id));
        });

        return $query;
    }

    public function constrainJoinedMeetings(Builder $query, ?int $requestedUnitId = null, string $attribution = 'INVOLVED_UNIT'): Builder
    {
        $this->assertUnitFilter($requestedUnitId);
        if ($requestedUnitId !== null) {
            return match ($attribution) {
                'REQUESTING_UNIT' => $query->where('meetings.organizing_unit_id', $requestedUnitId),
                'PARTICIPATING_UNIT' => $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('meeting_units')->whereColumn('meeting_units.meeting_id', 'meetings.id')->where('meeting_units.organizational_unit_id', $requestedUnitId)->where('meeting_units.role', 'PARTICIPATING')),
                default => $query->where(fn ($q) => $q->where('meetings.organizing_unit_id', $requestedUnitId)->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('meeting_units')->whereColumn('meeting_units.meeting_id', 'meetings.id')->where('meeting_units.organizational_unit_id', $requestedUnitId))),
            };
        }
        if ($this->allowsOrganizationBusiness()) return $query;
        $unitIds = $this->unitIds();
        $employeeId = $this->user->employee_id;
        return $query->where(function (Builder $q) use ($unitIds, $employeeId): void {
            if ($unitIds) {
                $q->whereIn('meetings.organizing_unit_id', $unitIds)
                    ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('meeting_units')->whereColumn('meeting_units.meeting_id', 'meetings.id')->whereIn('meeting_units.organizational_unit_id', $unitIds));
            }
            $q->orWhere('meetings.coordinator_id', $this->user->id)
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('meeting_participants')->whereColumn('meeting_participants.meeting_id', 'meetings.id')->where('meeting_participants.employee_id', $employeeId))
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('meeting_authority_contexts')->whereColumn('meeting_authority_contexts.meeting_id', 'meetings.id')->where('meeting_authority_contexts.authority_employee_id', $employeeId));
        });
    }

    public function constrainRequests(Builder $query, ?int $requestedUnitId = null): Builder
    {
        $this->assertUnitFilter($requestedUnitId);
        if ($requestedUnitId !== null) return $query->where('meeting_requests.requesting_unit_id', $requestedUnitId);
        if ($this->allowsOrganizationBusiness()) return $query;
        $unitIds = $this->unitIds();
        $query->where(function (Builder $q) use ($unitIds): void {
            if ($unitIds) $q->whereIn('meeting_requests.requesting_unit_id', $unitIds);
            $q->orWhere('meeting_requests.created_by', $this->user->id);
        });
        return $query;
    }

    public function constrainActionItems(Builder $query, ?int $requestedUnitId = null): Builder
    {
        $this->assertUnitFilter($requestedUnitId);
        if ($requestedUnitId !== null) return $query->where('meeting_action_items.responsible_unit_id', $requestedUnitId);
        if ($this->allowsOrganizationBusiness()) return $query;
        $unitIds = $this->unitIds();
        $query->where(function (Builder $q) use ($unitIds): void {
            if ($unitIds) $q->whereIn('meeting_action_items.responsible_unit_id', $unitIds);
            $q->orWhere('meeting_action_items.assigned_employee_id', $this->user->employee_id);
        });
        return $query;
    }

    private function applyUnitAttribution(Builder $query, int $unitId, string $attribution): Builder
    {
        return match ($attribution) {
            'REQUESTING_UNIT' => $query->where('meetings.organizing_unit_id', $unitId),
            'PARTICIPATING_UNIT' => $query->whereHas('units', fn (Builder $units) => $units->where('organizational_unit_id', $unitId)->where('role', 'PARTICIPATING')),
            default => $query->where(function (Builder $q) use ($unitId): void {
                $q->where('meetings.organizing_unit_id', $unitId)
                    ->orWhereHas('units', fn (Builder $units) => $units->where('organizational_unit_id', $unitId));
            }),
        };
    }
}
