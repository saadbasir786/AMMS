<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Meeting;

class MinutesReviewRequirementResolver
{
    public function resolve(Meeting $meeting): array
    {
        $flowType = (string) ($meeting->request?->flow_type ?? '');
        $units = $meeting->units()->whereIn('role', ['REQUESTER', 'PARTICIPATING'])->orderBy('organizational_unit_id')->get();
        $unitIds = $units->pluck('organizational_unit_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        if ($unitIds === []) $unitIds = [(int) $meeting->organizing_unit_id];

        $highAuthority = in_array($flowType, [
            'DIRECTORATE_WITH_HIGH_AUTHORITY',
            'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY',
            'HIGH_AUTHORITY_INITIATED',
        ], true) || $meeting->authorityContext()->exists();

        $authorityEmployeeId = $highAuthority ? (int) ($meeting->request?->authorityContext?->authority_employee_id ?? $meeting->authorityContext?->authority_employee_id ?? 0) : 0;
        $includeAuthority = $highAuthority && $authorityEmployeeId > 0;

        return [
            'flow_type' => $flowType,
            'unit_ids' => $unitIds,
            'requires_high_authority' => $includeAuthority,
            'authority_employee_id' => $includeAuthority ? $authorityEmployeeId : null,
        ];
    }
}
