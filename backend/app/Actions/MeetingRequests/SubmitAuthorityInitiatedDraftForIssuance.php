<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitAuthorityInitiatedDraftForIssuance
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            abort_unless($request->authorityContext && $this->actorCanPrepare($actor, (int) $request->authorityContext->authority_office_unit_id), 403, 'FORBIDDEN');
            abort_unless(in_array($request->status, [MeetingRequestStatus::DRAFT->value, MeetingRequestStatus::RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION->value], true), 422, 'DIRECTIVE_NOT_READY_FOR_ISSUANCE');
            $version = $this->currentVersion($request);
            $this->assertAuthorityInitiatedUnitSet($this->authorityInitiatedUnits($request));
            abort_unless(filled($version->subject) && filled($version->purpose) && filled($version->directive) && filled($version->requested_outcome), 422, 'DIRECTIVE_NOT_READY_FOR_ISSUANCE');
            $from = $this->transitionAuthorityInitiated($request, MeetingRequestStatus::PENDING_HIGH_AUTHORITY_ISSUANCE);
            $version->update(['version_status' => 'PENDING_ISSUANCE']);
            $this->recordAuthorityInitiated($request, $actor, 'DRAFT_SUBMITTED_FOR_AUTHORITY_ISSUANCE', $from, MeetingRequestStatus::PENDING_HIGH_AUTHORITY_ISSUANCE->value, null, ['directive_version' => $version->version]);
            $holder = $this->currentAuthorityHolder($request->authorityContext->organizationAuthority);
            $this->notifyUser($holder->user_id ?? null, 'مسودهٔ جلسه آماده صدور', 'یک دستور جلسه برای صدور مقام عالی آماده شده است.', $request);
            return $this->responseAuthorityInitiated($request);
        });
    }
}
