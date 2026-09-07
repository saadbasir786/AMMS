<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ParticipantCandidateQuery
{
    public function forActor(Meeting $meeting, User $actor): Builder
    {
        $owner = app(ParticipantGovernanceResolver::class)->actorOwner($meeting, $actor);
        $query = Employee::query()->with(['unit', 'position'])->where('status', 'ACTIVE');
        if (! $owner) {
            return $query->whereRaw('1 = 0');
        }
        $rootId = $owner['owner_type'] === 'AUTHORITY'
            ? (int) $meeting->authorityContext?->authority_office_unit_id : (int) $owner['owner_unit_id'];
        $root = OrganizationalUnit::find($rootId);
        if (! $root) {
            return $query->whereRaw('1 = 0');
        }
        $ids = [$rootId];
        $frontier = $ids;
        while ($frontier !== []) {
            $frontier = OrganizationalUnit::where('organization_id', $root->organization_id)
                ->whereIn('parent_id', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $query->where(function ($scope) use ($ids, $owner, $meeting): void {
            $scope->whereIn('organizational_unit_id', $ids);
            // Preserve visibility of the immutable authority participant.
            if ($owner['owner_type'] === 'AUTHORITY') {
                $scope->orWhere('id', $meeting->authorityContext?->authority_employee_id);
            }
        });
    }
}
