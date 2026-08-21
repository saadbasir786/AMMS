<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\MeetingAction;
use App\Models\MeetingParticipantChangeCase;
use App\Models\MeetingParticipantVersion;
use App\Models\MeetingParticipantVersionMember;
use App\Models\OrganizationalUnit;

class ParticipantVersionService
{
    public function ensureInitialVersion(Meeting $meeting, int $createdByUserId): MeetingParticipantVersion
    {
        $existing = $meeting->participantVersions()->where('version_number', 1)->first();
        if ($existing) {
            if (!$meeting->current_participant_version_id) $meeting->forceFill(['current_participant_version_id' => $existing->id])->save();
            return $existing->load('members.employee.unit');
        }

        $meeting->loadMissing(['units.unit', 'request', 'authorityContext', 'participants.employee.unit']);
        $formalUnits = $meeting->units->whereIn('role', ['REQUESTER', 'TARGET', 'REQUESTING', 'PARTICIPATING'])->values();
        $formalUnitIds = $formalUnits->pluck('organizational_unit_id')->map(fn ($id): int => (int) $id)->filter()->unique()->values();

        // FLOW-01 legacy/current conversion may not persist a meeting_units row. The organizing
        // Directorate is still the formal participant domain and must remain the ownership root.
        if ($formalUnitIds->isEmpty()) {
            $fallbackUnitId = (int) ($meeting->organizing_unit_id ?: $meeting->request?->requesting_unit_id ?: 0);
            if ($fallbackUnitId > 0) $formalUnitIds->push($fallbackUnitId);
        }
        $formalUnitModels = OrganizationalUnit::query()->whereIn('id', $formalUnitIds->all())->get()->keyBy('id');

        $authorityEmployeeId = (int) ($meeting->authorityContext?->authority_employee_id ?? 0);
        $authorityOfficeUnitId = (int) ($meeting->authorityContext?->authority_office_unit_id ?? 0);
        $authorityOffice = $authorityOfficeUnitId > 0 ? OrganizationalUnit::find($authorityOfficeUnitId) : null;

        $version = MeetingParticipantVersion::create([
            'meeting_id' => $meeting->id,
            'version_number' => 1,
            'source_change_case_id' => null,
            'created_by_user_id' => $createdByUserId,
            'created_at' => now(),
        ]);

        foreach ($meeting->participants as $participant) {
            $employeeUnitId = (int) $participant->organizational_unit_id;
            $isAuthority = $authorityEmployeeId > 0 && (int) $participant->employee_id === $authorityEmployeeId;
            $isAuthoritySide = $isAuthority || ($authorityOffice && $authorityOffice->isWithinScope($employeeUnitId));
            $formalOwnerUnitId = $formalUnitIds->first(function (int $unitId) use ($formalUnitModels, $employeeUnitId): bool {
                $unit = $formalUnitModels->get($unitId);
                return $unit && $unit->isWithinScope($employeeUnitId);
            });

            if (!$isAuthoritySide && !$formalOwnerUnitId) {
                throw new \RuntimeException('PARTICIPANT_OWNER_DOMAIN_NOT_FOUND');
            }

            MeetingParticipantVersionMember::create([
                'participant_version_id' => $version->id,
                'employee_id' => $participant->employee_id,
                'organizational_unit_id' => $employeeUnitId,
                'participant_role' => $participant->role,
                'owner_type' => $isAuthoritySide ? 'AUTHORITY' : 'UNIT',
                'owner_unit_id' => $isAuthoritySide ? null : (int) $formalOwnerUnitId,
                'is_mandatory' => $isAuthority,
                'created_at' => now(),
            ]);
        }
        $meeting->forceFill(['current_participant_version_id' => $version->id])->save();
        return $version->load('members.employee.unit');
    }

    public function createConfirmedVersion(Meeting $meeting, MeetingParticipantChangeCase $case, array $members, int $actorId): MeetingParticipantVersion
    {
        $oldVersion = $meeting->currentParticipantVersion;
        $nextNumber = ((int) $meeting->participantVersions()->max('version_number')) + 1;
        $version = MeetingParticipantVersion::create([
            'meeting_id' => $meeting->id,
            'version_number' => $nextNumber,
            'source_change_case_id' => $case->id,
            'created_by_user_id' => $actorId,
            'created_at' => now(),
        ]);

        foreach ($members as $member) {
            MeetingParticipantVersionMember::create([
                'participant_version_id' => $version->id,
                'employee_id' => $member['employee_id'],
                'organizational_unit_id' => $member['organizational_unit_id'],
                'participant_role' => $member['participant_role'],
                'owner_type' => $member['owner_type'],
                'owner_unit_id' => $member['owner_unit_id'],
                'is_mandatory' => $member['is_mandatory'],
                'created_at' => now(),
            ]);
        }

        $meeting->participants()->delete();
        foreach ($members as $member) {
            $meeting->participants()->create([
                'employee_id' => $member['employee_id'],
                'organizational_unit_id' => $member['organizational_unit_id'],
                'role' => $member['participant_role'],
            ]);
        }

        $meeting->forceFill([
            'current_participant_version_id' => $version->id,
            'active_participant_change_case_id' => null,
        ])->save();

        $operations = $case->items->map(fn ($item): array => [
            'operation' => $item->operation,
            'old_employee_id' => $item->old_employee_id,
            'new_employee_id' => $item->new_employee_id,
        ])->values()->all();

        MeetingAction::create([
            'meeting_id' => $meeting->id,
            'actor_id' => $actorId,
            'action' => 'MEETING_PARTICIPANTS_CHANGED',
            'comment' => $case->reason,
            'metadata' => [
                'old_participant_version_id' => $oldVersion?->id,
                'new_participant_version_id' => $version->id,
                'owner_type' => $case->owner_type,
                'owner_unit_id' => $case->owner_unit_id,
                'reason' => $case->reason,
                'operations' => $operations,
                'confirmed_by' => $actorId,
                'confirmed_at' => now()->toIso8601String(),
            ],
        ]);

        return $version->load('members.employee.unit');
    }
}
