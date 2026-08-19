<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateAuthorityInitiatedDraft
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $data): MeetingRequest {
            $this->assertActiveActor($actor);
            $unitIds = array_values(array_unique(array_map('intval', $data['participating_unit_ids'] ?? [])));
            $units = OrganizationalUnit::query()->whereIn('id', $unitIds)->get();
            abort_unless($units->count() === count($unitIds), 422, 'INVALID_PARTICIPATING_DIRECTORATE');
            $this->assertFlow06UnitSet($units);
            abort_if($units->contains(fn (OrganizationalUnit $unit) => $unit->unit_type === 'AUTHORITY_OFFICE'), 422, 'AUTHORITY_OFFICE_NOT_A_FORMAL_DIRECTORATE');
            $authority = $this->authorityForOrganization((int) $units->first()->organization_id);
            abort_unless($this->actorCanPrepare($actor, (int) $authority->authority_office_unit_id), 403, 'FORBIDDEN');

            $request = MeetingRequest::create([
                'flow_type' => 'HIGH_AUTHORITY_INITIATED',
                'requesting_unit_id' => null,
                'created_by' => $actor->id,
                'subject' => $data['subject'],
                'purpose' => $data['purpose'],
                'description' => $data['description'] ?? null,
                'directive' => $data['directive'],
                'meeting_justification' => $data['meeting_justification'] ?? null,
                'requested_outcome' => $data['requested_outcome'],
                'proposed_agenda' => $data['proposed_agenda'] ?? null,
                'priority' => $data['priority'] ?? 'NORMAL',
                'preferred_date' => $data['preferred_date'] ?? null,
                'preferred_start_time' => $data['preferred_start_time'] ?? null,
                'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? null,
                'proposed_location' => $data['preferred_location'] ?? null,
                'status' => MeetingRequestStatus::DRAFT->value,
                'lead_coordinator_unit_id' => $authority->authority_office_unit_id,
                'lead_coordinator_user_id' => $actor->id,
            ]);

            $request->units()->createMany($units->map(fn (OrganizationalUnit $unit) => [
                'organizational_unit_id' => $unit->id,
                'role' => 'PARTICIPATING',
            ])->all());

            $version = $request->directiveVersions()->create([
                'version' => 1,
                'version_status' => 'DRAFT',
                'subject' => $data['subject'],
                'purpose' => $data['purpose'],
                'description' => $data['description'] ?? null,
                'directive' => $data['directive'],
                'meeting_justification' => $data['meeting_justification'] ?? null,
                'requested_outcome' => $data['requested_outcome'],
                'priority' => $data['priority'] ?? 'NORMAL',
                'preferred_date' => $data['preferred_date'] ?? null,
                'preferred_start_time' => $data['preferred_start_time'] ?? null,
                'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? null,
                'preferred_location' => $data['preferred_location'] ?? null,
                'proposed_agenda' => $data['proposed_agenda'] ?? null,
                'participating_unit_ids' => $unitIds,
                'prepared_by' => $actor->id,
                'is_current' => true,
            ]);

            $request->authorityContext()->create([
                'organization_authority_id' => $authority->id,
                'authority_type' => $authority->authority_type,
                'origin_type' => 'HIGH_AUTHORITY_INITIATED',
                'authority_office_unit_id' => $authority->authority_office_unit_id,
                'authority_position_id' => $authority->authority_position_id,
                'office_review_status' => 'NOT_REQUIRED',
                'authority_decision_status' => 'PENDING_ISSUANCE',
                'current_directive_version_id' => $version->id,
            ]);

            $this->record06($request, $actor, 'AUTHORITY_INITIATED_DRAFT_CREATED', null, MeetingRequestStatus::DRAFT->value, null, ['directive_version' => 1, 'participating_unit_ids' => $unitIds]);
            return $this->response06($request);
        });
    }
}
