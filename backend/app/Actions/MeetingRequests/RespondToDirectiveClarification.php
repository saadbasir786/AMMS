<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RespondToDirectiveClarification
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $clarificationId, string $responseText): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $clarificationId, $responseText): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            abort_unless(filled(trim($responseText)), 422, 'RESPONSE_REQUIRED');
            $officeId = (int) ($request->authorityContext?->authority_office_unit_id ?? 0);
            abort_unless($officeId && $actor->can('authority-directive.clarification-respond') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('AUTHORITY_OFFICE', $officeId)), 403, 'FORBIDDEN');
            $clarification = $request->directiveClarifications()->whereNull('responded_at')->findOrFail($clarificationId);
            $clarification->update(['responded_by' => $actor->id, 'responded_at' => now(), 'response' => $responseText]);
            $request->unitResponses()->where('meeting_request_directive_version_id', $clarification->meeting_request_directive_version_id)->where('organizational_unit_id', $clarification->organizational_unit_id)->update(['response_status' => 'UNDER_COORDINATION']);
            if ($request->status === 'DIRECTORATE_CLARIFICATION_REQUIRED') $request->update(['status' => 'UNDER_DIRECTORATE_COORDINATION']);
            $this->record06($request, $actor, 'DIRECTIVE_CLARIFICATION_RESPONDED', $request->status, $request->status, $responseText, ['clarification_id' => $clarification->id]);
            return $this->response06($request);
        });
    }
}
