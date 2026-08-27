<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PrepareAuthorityDirectiveRevision
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            $officeId = (int) ($request->authorityContext?->authority_office_unit_id ?? 0);
            abort_unless($officeId && $actor->can('authority-initiated-meeting.prepare') && $actor->hasScopedRole('AUTHORITY_OFFICE', $officeId), 403, 'FORBIDDEN');
            abort_unless(in_array($request->status, [MeetingRequestStatus::ISSUED_BY_HIGH_AUTHORITY->value, MeetingRequestStatus::DISTRIBUTED_TO_DIRECTORATES->value, MeetingRequestStatus::UNDER_DIRECTORATE_COORDINATION->value, MeetingRequestStatus::DIRECTORATE_CLARIFICATION_REQUIRED->value, MeetingRequestStatus::DIRECTORATES_ACKNOWLEDGED->value], true), 422, 'MATERIAL_DIRECTIVE_CHANGE_REQUIRES_REISSUANCE');
            $previous = $this->currentVersion($request);
            $unitIds = array_values(array_unique(array_map('intval', $data['participating_unit_ids'] ?? $previous->participating_unit_ids ?? [])));
            $units = OrganizationalUnit::whereIn('id', $unitIds)->get();
            abort_unless($units->count() === count($unitIds), 422, 'INVALID_PARTICIPATING_DIRECTORATE');
            $this->assertAuthorityInitiatedUnitSet($units);
            abort_if($units->contains(fn (OrganizationalUnit $unit) => $unit->unit_type === 'AUTHORITY_OFFICE'), 422, 'AUTHORITY_OFFICE_NOT_A_FORMAL_DIRECTORATE');
            abort_unless((int) $units->first()->organization_id === (int) $request->authorityContext->organizationAuthority->organization_id, 422, 'CROSS_ORGANIZATION_AUTHORITY_INITIATED_MEETING_NOT_ALLOWED');
            $version = $request->directiveVersions()->create([
                'version' => ((int) $request->directiveVersions()->max('version')) + 1,
                'version_status' => 'PENDING_REISSUANCE',
                'subject' => $data['subject'] ?? $previous->subject, 'purpose' => $data['purpose'] ?? $previous->purpose,
                'description' => $data['description'] ?? $previous->description, 'directive' => $data['directive'] ?? $previous->directive,
                'meeting_justification' => $data['meeting_justification'] ?? $previous->meeting_justification,
                'requested_outcome' => $data['requested_outcome'] ?? $previous->requested_outcome,
                'priority' => $data['priority'] ?? $previous->priority, 'preferred_date' => $data['preferred_date'] ?? $previous->preferred_date,
                'preferred_start_time' => $data['preferred_start_time'] ?? $previous->preferred_start_time,
                'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? $previous->estimated_duration_minutes,
                'preferred_location' => $data['preferred_location'] ?? $previous->preferred_location,
                'proposed_agenda' => $data['proposed_agenda'] ?? $previous->proposed_agenda,
                'participating_unit_ids' => $unitIds, 'prepared_by' => $actor->id, 'is_current' => false,
            ]);
            $from = $this->transitionAuthorityInitiated($request, MeetingRequestStatus::PENDING_HIGH_AUTHORITY_REISSUANCE);
            $this->recordAuthorityInitiated($request, $actor, 'DIRECTIVE_REVISION_PREPARED', $from, MeetingRequestStatus::PENDING_HIGH_AUTHORITY_REISSUANCE->value, null, ['previous_version' => $previous->version, 'new_version' => $version->version, 'participating_unit_ids' => $unitIds]);
            return $this->responseAuthorityInitiated($request);
        });
    }
}