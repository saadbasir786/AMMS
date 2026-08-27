<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestDirectiveClarification
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId, $reason): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');
            abort_unless($request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists(), 403, 'DIRECTORATE_NOT_PART_OF_DIRECTIVE');
            abort_unless($this->actorCanClarify($actor, $unitId), 403, 'UNAUTHORIZED_DIRECTORATE_CLARIFICATION');
            abort_unless(in_array($request->status, [MeetingRequestStatus::DISTRIBUTED_TO_DIRECTORATES->value, MeetingRequestStatus::UNDER_DIRECTORATE_COORDINATION->value, MeetingRequestStatus::DIRECTORATE_CLARIFICATION_REQUIRED->value], true), 422, 'DIRECTIVE_NOT_DISTRIBUTED');
            $version = $this->currentVersion($request);
            $response = $request->unitResponses()->where('meeting_request_directive_version_id', $version->id)->where('organizational_unit_id', $unitId)->firstOrFail();
            $response->update(['response_status' => 'CLARIFICATION_REQUESTED']);
            $request->directiveClarifications()->create([
                'meeting_request_directive_version_id' => $version->id, 'organizational_unit_id' => $unitId,
                'requested_by' => $actor->id, 'requested_at' => now(), 'reason' => $reason,
            ]);
            if ($request->status !== MeetingRequestStatus::DIRECTORATE_CLARIFICATION_REQUIRED->value) {
                $from = $this->transitionAuthorityInitiated($request, MeetingRequestStatus::DIRECTORATE_CLARIFICATION_REQUIRED);
            } else { $from = $request->status; }
            $this->recordAuthorityInitiated($request, $actor, 'DIRECTORATE_CLARIFICATION_REQUESTED', $from, $request->status, $reason, ['organizational_unit_id' => $unitId, 'directive_version' => $version->version]);
            $this->notifyUnit((int) $request->authorityContext->authority_office_unit_id, ['AUTHORITY_OFFICE'], 'درخواست وضاحت برای دستور مقام', 'یکی از ریاست‌های دعوت‌شده درخواست وضاحت کرده است.', $request);
            return $this->responseAuthorityInitiated($request);
        });
    }
}
