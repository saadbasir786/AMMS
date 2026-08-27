<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnAuthorityInitiatedDraftForCorrection
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');
            $holder = $this->currentAuthorityHolder($request->authorityContext->organizationAuthority);
            abort_unless($this->actorIsHighAuthority($actor, $holder), 403, 'UNAUTHORIZED_HIGH_AUTHORITY_ISSUANCE');
            abort_unless($request->status === MeetingRequestStatus::PENDING_HIGH_AUTHORITY_ISSUANCE->value, 422, 'DIRECTIVE_NOT_READY_FOR_ISSUANCE');
            $from = $this->transitionAuthorityInitiated($request, MeetingRequestStatus::RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION);
            $version = $this->currentVersion($request);
            $version->update(['version_status' => 'DRAFT']);
            $this->recordAuthorityInitiated($request, $actor, 'AUTHORITY_DRAFT_RETURNED', $from, MeetingRequestStatus::RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION->value, $reason, ['directive_version' => $version->version]);
            $this->notifyUser($request->created_by, 'مسودهٔ جلسه برگشت داده شد', 'مسودهٔ دستور جلسه برای اصلاح برگشت داده شد.', $request);
            return $this->responseAuthorityInitiated($request);
        });
    }
}
