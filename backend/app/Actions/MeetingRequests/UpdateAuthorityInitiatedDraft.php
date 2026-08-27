<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateAuthorityInitiatedDraft
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            $authority = $request->authorityContext?->organizationAuthority;
            abort_unless($authority && $this->actorCanPrepare($actor, (int) $request->authorityContext->authority_office_unit_id), 403, 'FORBIDDEN');
            abort_unless(in_array($request->status, [MeetingRequestStatus::DRAFT->value, MeetingRequestStatus::RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION->value], true), 422, 'DRAFT_NOT_EDITABLE');
            $version = $this->currentVersion($request);
            abort_unless($version->version_status === 'DRAFT', 422, 'ISSUED_DIRECTIVE_IMMUTABLE');

            $unitIds = array_values(array_unique(array_map('intval', $data['participating_unit_ids'] ?? $version->participating_unit_ids ?? [])));
            $units = OrganizationalUnit::whereIn('id', $unitIds)->get();
            abort_unless($units->count() === count($unitIds), 422, 'INVALID_PARTICIPATING_DIRECTORATE');
            $this->assertAuthorityInitiatedUnitSet($units);
            abort_if($units->contains(fn (OrganizationalUnit $unit) => $unit->unit_type === 'AUTHORITY_OFFICE'), 422, 'AUTHORITY_OFFICE_NOT_A_FORMAL_DIRECTORATE');
            abort_unless((int) $units->first()->organization_id === (int) $authority->organization_id, 422, 'CROSS_ORGANIZATION_AUTHORITY_INITIATED_MEETING_NOT_ALLOWED');

            $values = [
                'subject' => $data['subject'] ?? $version->subject,
                'purpose' => $data['purpose'] ?? $version->purpose,
                'description' => $data['description'] ?? $version->description,
                'directive' => $data['directive'] ?? $version->directive,
                'meeting_justification' => $data['meeting_justification'] ?? $version->meeting_justification,
                'requested_outcome' => $data['requested_outcome'] ?? $version->requested_outcome,
                'proposed_agenda' => $data['proposed_agenda'] ?? $version->proposed_agenda,
                'priority' => $data['priority'] ?? $version->priority,
                'preferred_date' => $data['preferred_date'] ?? $version->preferred_date?->toDateString(),
                'preferred_start_time' => $data['preferred_start_time'] ?? $version->preferred_start_time,
                'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? $version->estimated_duration_minutes,
                'preferred_location' => $data['preferred_location'] ?? $version->preferred_location,
                'participating_unit_ids' => $unitIds,
                'prepared_by' => $actor->id,
            ];
            $version->update($values);
            $request->update([
                'subject' => $values['subject'], 'purpose' => $values['purpose'], 'description' => $values['description'],
                'directive' => $values['directive'], 'meeting_justification' => $values['meeting_justification'],
                'requested_outcome' => $values['requested_outcome'], 'proposed_agenda' => $values['proposed_agenda'],
                'priority' => $values['priority'], 'preferred_date' => $values['preferred_date'],
                'preferred_start_time' => $values['preferred_start_time'], 'estimated_duration_minutes' => $values['estimated_duration_minutes'],
                'proposed_location' => $values['preferred_location'],
            ]);
            $request->units()->delete();
            $request->units()->createMany($units->map(fn (OrganizationalUnit $unit) => ['organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING'])->all());
            $this->recordAuthorityInitiated($request, $actor, 'AUTHORITY_INITIATED_DRAFT_UPDATED', $request->status, $request->status, null, ['directive_version' => $version->version, 'participating_unit_ids' => $unitIds]);
            return $this->responseAuthorityInitiated($request);
        });
    }
}
