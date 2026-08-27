<?php

namespace App\Support;

use App\Models\Meeting;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ScheduleGovernanceResolver
{
    public function resolve(Meeting $meeting): array
    {
        $flowType = (string) ($meeting->request?->flow_type ?? 'INTERNAL_DIRECTORATE');
        $mode = match ($flowType) {
            'INTERNAL_DIRECTORATE' => 'INTERNAL_SINGLE_UNIT',
            'INTER_DIRECTORATE', 'MULTI_DIRECTORATE' => 'PEER_MULTI_UNIT',
            'DIRECTORATE_WITH_HIGH_AUTHORITY', 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY', 'HIGH_AUTHORITY_INITIATED' => 'AUTHORITY_CONTROLLED',
            default => throw new InvalidArgumentException("Unsupported meeting reschedule governance flow type: {$flowType}"),
        };

        $units = $meeting->relationLoaded('units') ? $meeting->units : $meeting->units()->get();
        $formalUnits = $units->whereIn('role', ['REQUESTING', 'REQUESTER', 'PARTICIPATING'])->values();
        if ($formalUnits->isEmpty()) {
            throw new InvalidArgumentException('MEETING_HAS_NO_FORMAL_UNITS');
        }

        $requiredUnits = $flowType === 'HIGH_AUTHORITY_INITIATED'
            ? $formalUnits->where('role', 'PARTICIPATING')->values()
            : $formalUnits;

        return [
            'flow_type' => $flowType,
            'mode' => $mode,
            'formal_units' => $formalUnits,
            'required_units' => $requiredUnits,
            'required_unit_ids' => $requiredUnits->pluck('organizational_unit_id')->map(fn ($id): int => (int) $id)->values()->all(),
            'authority_controlled' => $mode === 'AUTHORITY_CONTROLLED',
            'peer_negotiation' => $mode === 'PEER_MULTI_UNIT',
            'authority_office_unit_id' => (int) ($meeting->authorityContext?->authority_office_unit_id ?? 0),
            'authority_employee_id' => (int) ($meeting->authorityContext?->authority_employee_id ?? 0),
        ];
    }

    public function requiredUnits(Meeting $meeting): Collection
    {
        return collect($this->resolve($meeting)['required_units']);
    }

    public function isAuthorityControlled(Meeting $meeting): bool
    {
        return $this->resolve($meeting)['authority_controlled'];
    }
}
