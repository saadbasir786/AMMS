<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartAuthorityDirectiveCoordination
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            abort_unless($request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists(), 403, 'DIRECTORATE_NOT_PART_OF_DIRECTIVE');
            abort_unless($this->actorCoordinates($actor, $unitId), 403, 'UNAUTHORIZED_DIRECTORATE_COORDINATION');
            abort_unless(in_array($request->status, [MeetingRequestStatus::DISTRIBUTED_TO_DIRECTORATES->value, MeetingRequestStatus::UNDER_DIRECTORATE_COORDINATION->value, MeetingRequestStatus::DIRECTORATE_CLARIFICATION_REQUIRED->value], true), 422, 'DIRECTIVE_NOT_DISTRIBUTED');
            $version = $this->currentVersion($request);
            $response = $request->unitResponses()->where('meeting_request_directive_version_id', $version->id)->where('organizational_unit_id', $unitId)->firstOrFail();
            abort_unless(in_array($response->response_status, ['PENDING_ACKNOWLEDGEMENT', 'CLARIFICATION_REQUESTED'], true), 422, 'UNIT_RESPONSE_NOT_OPEN');
            $response->update(['response_status' => 'UNDER_COORDINATION', 'coordination_started_by' => $actor->id, 'coordination_started_at' => now()]);
            if ($request->status !== MeetingRequestStatus::UNDER_DIRECTORATE_COORDINATION->value) $this->transitionAuthorityInitiated($request, MeetingRequestStatus::UNDER_DIRECTORATE_COORDINATION);
            $this->recordAuthorityInitiated($request, $actor, 'DIRECTORATE_COORDINATION_STARTED', $request->status, $request->status, null, ['organizational_unit_id' => $unitId, 'directive_version' => $version->version]);
            return $this->responseAuthorityInitiated($request);
        });
    }
}
