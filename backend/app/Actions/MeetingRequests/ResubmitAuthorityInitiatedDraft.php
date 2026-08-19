<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResubmitAuthorityInitiatedDraft
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $request = $this->lockFlow06Request($request);
            abort_unless($request->authorityContext && $this->actorCanPrepare($actor, (int) $request->authorityContext->authority_office_unit_id), 403, 'FORBIDDEN');
            abort_unless($request->status === MeetingRequestStatus::RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION->value, 422, 'DIRECTIVE_NOT_READY_FOR_ISSUANCE');
            $version = $this->currentVersion($request);
            abort_unless($version->version_status === 'RETURNED_FOR_CORRECTION', 422, 'DIRECTIVE_NOT_READY_FOR_ISSUANCE');
            $from = $this->transition06($request, MeetingRequestStatus::PENDING_HIGH_AUTHORITY_ISSUANCE);
            $version->update(['version_status' => 'PENDING_ISSUANCE', 'prepared_by' => $actor->id]);
            $this->record06($request, $actor, 'AUTHORITY_DRAFT_RESUBMITTED', $from, MeetingRequestStatus::PENDING_HIGH_AUTHORITY_ISSUANCE->value, null, ['directive_version' => $version->version]);
            return $this->response06($request);
        });
    }
}
