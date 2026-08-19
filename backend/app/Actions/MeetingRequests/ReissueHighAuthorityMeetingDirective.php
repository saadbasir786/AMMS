<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReissueHighAuthorityMeetingDirective
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $request = $this->lockFlow06Request($request);
            $authority = $request->authorityContext?->organizationAuthority;
            abort_unless($authority, 422, 'HIGH_AUTHORITY_NOT_CONFIGURED');
            $holder = $this->currentAuthorityHolder($authority);
            abort_unless($this->actorIsHighAuthority($actor, $holder), 403, 'UNAUTHORIZED_HIGH_AUTHORITY_ISSUANCE');
            abort_unless($request->status === MeetingRequestStatus::PENDING_HIGH_AUTHORITY_REISSUANCE->value, 422, 'MATERIAL_DIRECTIVE_CHANGE_REQUIRES_REISSUANCE');
            $previous = $request->directiveVersions()->where('is_current', true)->firstOrFail();
            $version = $request->directiveVersions()->where('version_status', 'PENDING_REISSUANCE')->latest('version')->firstOrFail();
            $from = $this->transition06($request, MeetingRequestStatus::ISSUED_BY_HIGH_AUTHORITY);
            $previous->update(['is_current' => false]);
            $version->update([
                'version_status' => 'ISSUED', 'is_current' => true, 'issued_by' => $actor->id,
                'authority_employee_id' => $holder->id, 'authority_position_id' => $authority->authority_position_id, 'issued_at' => now(),
            ]);
            $request->update([
                'subject' => $version->subject, 'purpose' => $version->purpose, 'description' => $version->description,
                'directive' => $version->directive, 'meeting_justification' => $version->meeting_justification,
                'requested_outcome' => $version->requested_outcome, 'proposed_agenda' => $version->proposed_agenda,
                'priority' => $version->priority, 'preferred_date' => $version->preferred_date,
                'preferred_start_time' => $version->preferred_start_time, 'estimated_duration_minutes' => $version->estimated_duration_minutes,
                'proposed_location' => $version->preferred_location,
            ]);
            $request->units()->delete();
            foreach ($version->participating_unit_ids as $unitId) $request->units()->create(['organizational_unit_id' => (int) $unitId, 'role' => 'PARTICIPATING']);
            $request->authorityContext()->update(['authority_employee_id' => $holder->id, 'current_directive_version_id' => $version->id, 'authority_decision_status' => 'REISSUED', 'authority_decided_by' => $actor->id, 'authority_decided_at' => now()]);
            $this->record06($request, $actor, 'HIGH_AUTHORITY_DIRECTIVE_REISSUED', $from, MeetingRequestStatus::ISSUED_BY_HIGH_AUTHORITY->value, null, ['previous_version' => $previous->version, 'directive_version' => $version->version]);
            return $this->response06($request);
        });
    }
}
