<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parents = DB::table('organizational_units')->pluck('parent_id', 'id')
            ->mapWithKeys(fn ($parentId, $id): array => [(int) $id => $parentId ? (int) $parentId : null])
            ->all();

        $meetings = DB::table('meetings')->orderBy('id')->get();

        foreach ($meetings as $meeting) {
            $formalUnitIds = DB::table('meeting_units')
                ->where('meeting_id', $meeting->id)
                ->whereIn('role', ['REQUESTING', 'PARTICIPATING'])
                ->pluck('organizational_unit_id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $context = DB::table('meeting_authority_contexts')->where('meeting_id', $meeting->id)->first();
            $authorityEmployeeId = (int) ($context->authority_employee_id ?? 0);
            $authorityOfficeUnitId = (int) ($context->authority_office_unit_id ?? 0);

            $versionIds = DB::table('meeting_participant_versions')
                ->where('meeting_id', $meeting->id)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($versionIds === []) continue;

            $members = DB::table('meeting_participant_version_members as member')
                ->join('employees as employee', 'employee.id', '=', 'member.employee_id')
                ->whereIn('member.participant_version_id', $versionIds)
                ->select('member.id', 'member.employee_id', 'employee.organizational_unit_id')
                ->orderBy('member.id')
                ->get();

            foreach ($members as $member) {
                $employeeUnitId = (int) $member->organizational_unit_id;
                $isAuthorityEmployee = $authorityEmployeeId > 0 && (int) $member->employee_id === $authorityEmployeeId;
                $isAuthorityOfficeMember = $authorityOfficeUnitId > 0 && $this->isWithin($employeeUnitId, $authorityOfficeUnitId, $parents);

                if ($isAuthorityEmployee || $isAuthorityOfficeMember) {
                    DB::table('meeting_participant_version_members')->where('id', $member->id)->update([
                        'owner_type' => 'AUTHORITY',
                        'owner_unit_id' => null,
                        'is_mandatory' => $isAuthorityEmployee,
                    ]);
                    continue;
                }

                $formalOwnerUnitId = collect($formalUnitIds)
                    ->first(fn (int $unitId): bool => $this->isWithin($employeeUnitId, $unitId, $parents));

                if (!$formalOwnerUnitId) {
                    throw new RuntimeException('FLOW08_PARTICIPANT_OWNER_UNRESOLVED: meeting='.$meeting->id.', employee='.$member->employee_id);
                }

                DB::table('meeting_participant_version_members')->where('id', $member->id)->update([
                    'owner_type' => 'UNIT',
                    'owner_unit_id' => $formalOwnerUnitId,
                    'is_mandatory' => false,
                ]);
            }
        }
    }

    private function isWithin(int $unitId, int $scopeUnitId, array $parents): bool
    {
        $current = $unitId;
        $visited = [];

        while ($current > 0 && !isset($visited[$current])) {
            if ($current === $scopeUnitId) return true;
            $visited[$current] = true;
            $current = (int) ($parents[$current] ?? 0);
        }

        return false;
    }

    public function down(): void
    {
        // Data normalization is intentionally irreversible: restoring ambiguous legacy ownership
        // would reintroduce invalid authority/unit attribution.
    }
};
