<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IssueHighAuthorityMeetingDirective
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
            abort_unless($request->status === MeetingRequestStatus::PENDING_HIGH_AUTHORITY_ISSUANCE->value, 422, 'DIRECTIVE_NOT_READY_FOR_ISSUANCE');
            $version = $this->currentVersion($request);
            $this->assertFlow06UnitSet($this->flow06Units($request));
            abort_unless(filled($version->subject) && filled($version->purpose) && filled($version->directive) && filled($version->requested_outcome) && filled($version->estimated_duration_minutes), 422, 'DIRECTIVE_NOT_READY_FOR_ISSUANCE');
            $from = $this->transition06($request, MeetingRequestStatus::ISSUED_BY_HIGH_AUTHORITY);
            $version->update([
                'version_status' => 'ISSUED', 'issued_by' => $actor->id, 'authority_employee_id' => $holder->id,
                'authority_position_id' => $authority->authority_position_id, 'issued_at' => now(), 'is_current' => true,
            ]);
            $request->authorityContext()->update([
                'authority_employee_id' => $holder->id, 'origin_type' => 'HIGH_AUTHORITY_INITIATED',
                'authority_decision_status' => 'ISSUED', 'authority_decided_by' => $actor->id,
                'authority_decided_at' => now(), 'current_directive_version_id' => $version->id,
            ]);
            $this->record06($request, $actor, 'HIGH_AUTHORITY_DIRECTIVE_ISSUED', $from, MeetingRequestStatus::ISSUED_BY_HIGH_AUTHORITY->value, null, ['directive_version' => $version->version, 'authority_employee_id' => $holder->id, 'authority_position_id' => $authority->authority_position_id]);
            return $this->response06($request);
        });
    }
}
