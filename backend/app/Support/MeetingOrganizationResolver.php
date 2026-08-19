<?php

namespace App\Support;

use App\Models\Meeting;

class MeetingOrganizationResolver
{
    public function id(Meeting $meeting): ?int
    {
        $unit = $meeting->relationLoaded('units')
            ? $meeting->units->first(fn ($item) => (int) $item->organizational_unit_id === (int) $meeting->organizing_unit_id)
                ?? $meeting->units->first(fn ($item) => in_array($item->role, ['REQUESTING', 'PARTICIPATING'], true))
            : $meeting->units()->with('unit')->when($meeting->organizing_unit_id, fn ($q) => $q->where('organizational_unit_id', $meeting->organizing_unit_id))->first()
                ?? $meeting->units()->with('unit')->whereIn('role', ['REQUESTING', 'PARTICIPATING'])->first();

        return $unit?->unit?->organization_id ? (int) $unit->unit->organization_id : null;
    }
}
